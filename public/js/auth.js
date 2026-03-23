// public/js/auth.js

// ================================================
// FONCTIONS UTILITAIRES — Conversion des données
// ================================================

/**
 * Convertit un ArrayBuffer en base64url
 * WebAuthn travaille avec des ArrayBuffer, mais JSON ne peut pas
 * les transmettre directement — on les convertit en base64url
 */
function bufferToBase64Url(buffer) {
    const bytes  = Array.from(new Uint8Array(buffer));
    const binary = bytes.map(b => String.fromCharCode(b)).join('');
    return btoa(binary)
        .replace(/\+/g, '-')   // + devient -
        .replace(/\//g, '_')   // / devient _
        .replace(/=+$/, '');   // supprime le padding =
}

/**
 * Convertit base64url en ArrayBuffer
 * Fait l'inverse de bufferToBase64Url
 */
function base64UrlToBuffer(base64url) {
    let base64 = base64url
        .replace(/-/g, '+')    // - redevient +
        .replace(/_/g, '/');   // _ redevient /

    // Ajoute le padding manquant
    const padding = '='.repeat((4 - base64.length % 4) % 4);
    base64 += padding;

    const binary = atob(base64);
    const bytes  = Uint8Array.from(binary, c => c.charCodeAt(0));
    return bytes.buffer;
}

// ================================================
// PARTIE 1 : INSCRIPTION AVEC PASSKEY
// ================================================

/**
 * Inscrit un utilisateur avec une Passkey
 * Appelée depuis le formulaire d'inscription
 *
 * @param {string} email       - Email de l'utilisateur
 * @param {string} displayName - Nom affiché (optionnel)
 */
async function registerPasskey(email, displayName) {

    // ---- ÉTAPE 1 : Demande les options au serveur Symfony ----
    const optionsRes = await fetch('/api/auth/register/options', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ email, displayName })
    });

    if (!optionsRes.ok) {
        const err = await optionsRes.json();
        throw new Error(err.error || 'Échec options inscription');
    }

    const options = await optionsRes.json();

    // ---- ÉTAPE 2 : Le navigateur crée la Passkey ----
    // navigator.credentials.create() ouvre la boîte de dialogue
    // biométrique (Windows Hello, Face ID, Touch ID...)
    const credential = await navigator.credentials.create({
        publicKey: {
            ...options,
            // Le challenge doit être un ArrayBuffer
            challenge: base64UrlToBuffer(options.challenge),
            user: {
                ...options.user,
                // L'ID utilisateur doit être un ArrayBuffer
                id: base64UrlToBuffer(options.user.id)
            },
            // Exclut les credentials déjà enregistrés
            excludeCredentials: options.excludeCredentials?.map(c => ({
                ...c,
                id: base64UrlToBuffer(c.id)
            }))
        }
    });

    // ---- ÉTAPE 3 : Envoie la réponse au serveur pour vérification ----
    const verifyRes = await fetch('/api/auth/register/verify', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({
            email,
            credential: {
                id:    credential.id,
                rawId: bufferToBase64Url(credential.rawId),
                response: {
                    // Données du client (navigateur)
                    clientDataJSON:    bufferToBase64Url(credential.response.clientDataJSON),
                    // Données de l'authentificateur (Windows Hello, etc.)
                    attestationObject: bufferToBase64Url(credential.response.attestationObject)
                },
                type:                 credential.type,
                clientExtensionResults: credential.getClientExtensionResults()
            }
        })
    });

    const result = await verifyRes.json();
    if (!verifyRes.ok) throw new Error(result.error || 'Échec vérification inscription');

    // ---- ÉTAPE 4 : Sauvegarde les tokens JWT en localStorage ----
    if (result.token) {
        localStorage.setItem('jwt_token',     result.token);
        localStorage.setItem('refresh_token', result.refresh_token);
    }

    return result;
}

// ================================================
// PARTIE 2 : CONNEXION AVEC PASSKEY
// ================================================

/**
 * Connecte un utilisateur avec sa Passkey existante
 * Appelée depuis le formulaire de connexion
 */
async function loginWithPasskey() {

    // ---- ÉTAPE 1 : Demande les options de connexion au serveur ----
    const optionsRes = await fetch('/api/auth/login/options', {
        method: 'POST'
    });

    if (!optionsRes.ok) {
        const err = await optionsRes.json();
        throw new Error(err.error || 'Échec options connexion');
    }

    const options = await optionsRes.json();

    // ---- ÉTAPE 2 : Le navigateur demande la biométrie à l'utilisateur ----
    // Ouvre Windows Hello, Face ID, Touch ID...
    const assertion = await navigator.credentials.get({
        publicKey: {
            ...options,
            // Le challenge doit être un ArrayBuffer
            challenge: base64UrlToBuffer(options.challenge),
            allowCredentials: options.allowCredentials?.map(c => ({
                ...c,
                id: base64UrlToBuffer(c.id)
            }))
        }
    });

    // ---- ÉTAPE 3 : Envoie la signature au serveur pour vérification ----
    const verifyRes = await fetch('/api/auth/login/verify', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({
            credential: {
                id:    assertion.id,
                rawId: bufferToBase64Url(assertion.rawId),
                response: {
                    clientDataJSON:    bufferToBase64Url(assertion.response.clientDataJSON),
                    authenticatorData: bufferToBase64Url(assertion.response.authenticatorData),
                    signature:         bufferToBase64Url(assertion.response.signature),
                    // userHandle peut être null si l'utilisateur n'est pas identifié
                    userHandle: assertion.response.userHandle
                        ? bufferToBase64Url(assertion.response.userHandle)
                        : null
                },
                type:                   assertion.type,
                clientExtensionResults: assertion.getClientExtensionResults()
            }
        })
    });

    const result = await verifyRes.json();
    if (!verifyRes.ok) throw new Error(result.error || 'Échec authentification');

    // ---- ÉTAPE 4 : Sauvegarde les tokens JWT ----
    if (result.token) {
        localStorage.setItem('jwt_token',     result.token);
        localStorage.setItem('refresh_token', result.refresh_token);
    }

    return result;
}

// ================================================
// PARTIE 3 : UTILITAIRES JWT
// ================================================

/**
 * Ajoute automatiquement le token JWT à chaque requête fetch
 * Utilise cette fonction à la place de fetch() dans ton code
 *
 * Exemple : authFetch('/api/events') au lieu de fetch('/api/events')
 */
function authFetch(url, options = {}) {
    const token   = localStorage.getItem('jwt_token');
    const headers = {
        ...(options.headers || {}),
        'Authorization': token ? `Bearer ${token}` : ''
    };
    return fetch(url, { ...options, headers });
}

/**
 * Rafraîchit le token JWT expiré automatiquement
 * Appelée quand une requête retourne 401 (non autorisé)
 */
async function refreshToken() {
    const refresh = localStorage.getItem('refresh_token');
    if (!refresh) return false;

    const res = await fetch('/api/token/refresh', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ refresh_token: refresh })
    });

    if (!res.ok) {
        // Token expiré — déconnecte l'utilisateur
        localStorage.removeItem('jwt_token');
        localStorage.removeItem('refresh_token');
        return false;
    }

    const data = await res.json();
    localStorage.setItem('jwt_token', data.token);

    if (data.refresh_token) {
        localStorage.setItem('refresh_token', data.refresh_token);
    }

    return true;
}

/**
 * Déconnecte l'utilisateur en supprimant les tokens
 */
function logout() {
    localStorage.removeItem('jwt_token');
    localStorage.removeItem('refresh_token');
    window.location.href = '/login';
}
