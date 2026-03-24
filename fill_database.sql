-- Insérer les utilisateurs avec des mots de passe hachés
-- Mot de passe "admin123" haché
INSERT INTO user (id, email, roles, password, is_verified) 
VALUES (UNHEX(REPLACE(UUID(), '-', '')), 'admin@admin.com', '["ROLE_ADMIN"]', '$2y$10$6tzDHRr4QcADLUJ9r.BlJuT8G/NeY3LgxdzK/2tWQbVi.zTKmGkQm', 1);

-- Mot de passe "user123" haché
INSERT INTO user (id, email, roles, password, is_verified) 
VALUES (UNHEX(REPLACE(UUID(), '-', '')), 'user@test.com', '["ROLE_USER"]', '$2y$10$9a5ooD7H5ZRsOUqCBD/ZMu7luPItAUqXRUL0mDgeBDyfUnNhN8VoK', 1);

-- Mot de passe "passkey123" haché
INSERT INTO user (id, email, roles, password, is_verified) 
VALUES (UNHEX(REPLACE(UUID(), '-', '')), 'attiasabrine450@gmail.com', '["ROLE_USER"]', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

-- Insérer les événements
INSERT INTO event (title, description, date, location, seats, image) VALUES
('Concert Jazz Night', 'Une soirée jazz exceptionnelle avec les meilleurs musiciens tunisiens.', DATE_ADD(NOW(), INTERVAL 7 DAY), 'Salle des fêtes, Tunis', 100, 'https://picsum.photos/id/104/800/400'),
('Conférence Tech 2026', 'Les dernières tendances en intelligence artificielle et développement web.', DATE_ADD(NOW(), INTERVAL 14 DAY), 'Centre de congrès, Sousse', 200, 'https://picsum.photos/id/0/800/400'),
('Festival Culturel', 'Célébration de la culture tunisienne avec musique, danse et gastronomie.', DATE_ADD(NOW(), INTERVAL 21 DAY), 'Amphithéâtre, Carthage', 500, 'https://picsum.photos/id/30/800/400'),
('Workshop Symfony', 'Apprenez Symfony 7 avec des experts. JWT, Passkeys et Docker.', DATE_ADD(NOW(), INTERVAL 3 DAY), 'ISSAT Sousse', 30, 'https://picsum.photos/id/1/800/400'),
('Soirée Startup', 'Rencontrez les startups tunisiennes. Pitchs et networking.', DATE_ADD(NOW(), INTERVAL 10 DAY), 'Hub Innovant, Sfax', 150, 'https://picsum.photos/id/26/800/400'),
('Nuit du Code Tunisien', 'Une nuit de programmation intense avec des challenges de code.', DATE_ADD(NOW(), INTERVAL 5 DAY), 'Technopark El Ghazala, Ariana', 28, 'https://picsum.photos/id/100/800/400');