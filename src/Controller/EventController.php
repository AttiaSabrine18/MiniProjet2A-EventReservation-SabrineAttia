<?php
// src/Controller/EventController.php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Reservations;
use App\Form\EventType;
use App\Form\ReservationFormType;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EventController extends AbstractController
{
    // ================================================
    // PARTIE 1 : ROUTES PUBLIQUES (utilisateurs)
    // ================================================

    /**
     * Page d'accueil — Liste des événements
     * URL : /
     */
    #[Route('/', name: 'app_home')]
    public function index(EventRepository $eventRepository): Response
    {
        $events = $eventRepository->findBy([], ['date' => 'ASC']);

        return $this->render('event/index.html.twig', [
            'events' => $events,
        ]);
    }

    /**
     * Détail d'un événement
     * URL : /event/{id}
     */
    #[Route('/event/{id}', name: 'app_event_show')]
    public function show(Event $event): Response
    {
        return $this->render('event/show.html.twig', [
            'event' => $event,
        ]);
    }

    /**
     * Formulaire de réservation
     * GET  /event/{id}/reserve → Affiche le formulaire
     * POST /event/{id}/reserve → Traite la réservation
     */
    #[Route('/event/{id}/reserve', name: 'app_event_reserve', methods: ['GET', 'POST'])]
    public function reserve(
        Event $event,
        Request $request,
        EntityManagerInterface $em
    ): Response {

        // Vérifie qu'il reste des places
        if ($event->getSeats() <= 0) {
            $this->addFlash('error', 'Désolé, cet événement est complet !');
            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        // Crée une réservation vide
        $reservation = new Reservations();
        $reservation->setEvent($event);
        $reservation->setCreatedAt(new \DateTimeImmutable());

        $form = $this->createForm(ReservationFormType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // Réduit les places disponibles
            $event->setSeats($event->getSeats() - 1);

            $em->persist($reservation);
            $em->flush();

            $this->addFlash(
                'success',
                'Réservation confirmée ! Merci ' . $reservation->getName()
            );

            return $this->redirectToRoute(
                'app_reservation_confirmation',
                ['id' => $reservation->getId()]
            );
        }

        return $this->render('event/reserve.html.twig', [
            'event' => $event,
            'form'  => $form,
        ]);
    }

    /**
     * Page de confirmation après réservation
     * URL : /reservation/{id}/confirmation
     */
    #[Route('/reservation/{id}/confirmation', name: 'app_reservation_confirmation')]
    public function confirmation(Reservations $reservation): Response
    {
        return $this->render('event/confirmation.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    // ================================================
    // PARTIE 2 : ROUTES ADMIN (CRUD complet)
    // ================================================

    /**
     * Liste tous les événements — Admin
     * URL : /admin/event
     */
    #[Route('/admin/event', name: 'admin_event_index', methods: ['GET'])]
    public function adminIndex(EventRepository $eventRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/event/index.html.twig', [
            'events' => $eventRepository->findAll(),
        ]);
    }

    /**
     * Créer un nouvel événement — Admin
     * URL : /admin/event/new
     */
    #[Route('/admin/event/new', name: 'admin_event_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $event = new Event();
        $form  = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // ====== Gère l'upload de l'image ======
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                // Génère un nom unique pour éviter les conflits
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();

                // Déplace le fichier dans public/uploads/events/
                $imageFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/events',
                    $newFilename
                );

                // Sauvegarde le nom du fichier en base
                $event->setImage($newFilename);
            }

            $em->persist($event);
            $em->flush();

            $this->addFlash('success', ' Événement créé avec succès !');
            return $this->redirectToRoute('admin_event_index');
        }

        return $this->render('admin/event/new.html.twig', [
            'event' => $event,
            'form'  => $form,
        ]);
    }

    /**
     * Modifier un événement — Admin
     * URL : /admin/event/{id}/edit
     */
    #[Route('/admin/event/{id}/edit', name: 'admin_event_edit', methods: ['GET', 'POST'])]
    public function edit(
        Event $event,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // ====== Gère l'upload de la nouvelle image ======
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {

                // Supprime l'ancienne image si elle est locale (pas une URL)
                if ($event->getImage() && !str_starts_with($event->getImage(), 'http')) {
                    $oldFile = $this->getParameter('kernel.project_dir')
                               . '/public/uploads/events/'
                               . $event->getImage();
                    if (file_exists($oldFile)) {
                        unlink($oldFile); // Supprime l'ancien fichier
                    }
                }

                // Génère un nouveau nom unique
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();

                // Déplace le nouveau fichier
                $imageFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/events',
                    $newFilename
                );

                $event->setImage($newFilename);
            }

            $em->flush();
            $this->addFlash('success', ' Événement modifié avec succès !');
            return $this->redirectToRoute('admin_event_index');
        }

        return $this->render('admin/event/edit.html.twig', [
            'event' => $event,
            'form'  => $form,
        ]);
    }

    /**
     * Supprimer un événement — Admin
     * URL : /admin/event/{id}/delete
     */
    #[Route('/admin/event/{id}/delete', name: 'admin_event_delete', methods: ['POST'])]
    public function delete(
        Event $event,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete' . $event->getId(), $request->request->get('_token'))) {

            // Supprime l'image locale si elle existe
            if ($event->getImage() && !str_starts_with($event->getImage(), 'http')) {
                $oldFile = $this->getParameter('kernel.project_dir')
                           . '/public/uploads/events/'
                           . $event->getImage();
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }

            $em->remove($event);
            $em->flush();
            $this->addFlash('success', 'Événement supprimé avec succès !');
        }

        return $this->redirectToRoute('admin_event_index');
    }

    /**
     * Voir les réservations d'un événement — Admin
     * URL : /admin/event/{id}/reservations
     */
    #[Route('/admin/event/{id}/reservations', name: 'admin_event_reservations', methods: ['GET'])]
    public function reservations(Event $event): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/event/reservations.html.twig', [
            'event'        => $event,
            'reservations' => $event->getReservations(),
        ]);
    }

    /**
 * Liste toutes les réservations — Admin
 * URL : /admin/reservations
 */
#[Route('/admin/reservations', name: 'admin_reservations_index', methods: ['GET'])]
public function allReservations(
    \App\Repository\ReservationsRepository $reservationsRepository
): Response {
    $this->denyAccessUnlessGranted('ROLE_ADMIN');

    return $this->render('admin/reservations/index.html.twig', [
        'reservations' => $reservationsRepository->findBy(
            [],
            ['created_at' => 'DESC'] // Plus récentes en premier
        ),
    ]);
}


}