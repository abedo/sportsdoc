<?php

namespace App\Controller;

use App\Entity\Place;
use App\Entity\User;
use App\Form\PlaceType;
use App\Repository\AttendanceRepository;
use App\Repository\GameRepository;
use App\Repository\PlaceRepository;
use App\Service\BalanceCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/place')]
final class PlaceController extends AbstractController
{
    /** Ekran startowy: miejsca jako przewijane karty (strzałki, klawiatura, przesunięcie palcem). */
    #[Route('/', name: 'place_index', methods: ['GET'])]
    public function index(
        Request $request,
        PlaceRepository $placeRepository,
        GameRepository $gameRepository,
        AttendanceRepository $attendanceRepository,
        BalanceCalculator $balanceCalculator,
    ): Response {
        $places = $placeRepository->findBy([], ['name' => 'ASC']);
        $presentCounts = $attendanceRepository->presentCountsByGame();

        $cards = [];
        $activeIndex = 0;
        $wanted = (int) filter_var($request->query->all()['place'] ?? null, FILTER_VALIDATE_INT);
        foreach ($places as $i => $place) {
            if ($place->getId() === $wanted) {
                $activeIndex = $i;
            }
            $cards[] = [
                'place' => $place,
                'games' => $gameRepository->latestForPlace($place, 5),
                'gamesTotal' => $place->getGames()->count(),
                'people' => $attendanceRepository->distinctAttendeesForPlace($place),
            ];
        }

        $me = $this->getUser();

        return $this->render('place/index.html.twig', [
            'cards' => $cards,
            'activeIndex' => $activeIndex,
            'presentCounts' => $presentCounts,
            'balance' => $me instanceof User ? $balanceCalculator->forUser($me) : null,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/new', name: 'place_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $place = new Place();
        $form = $this->createForm(PlaceType::class, $place);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($place);
            $entityManager->flush();

            return $this->redirectToRoute('place_index', ['place' => $place->getId()]);
        }

        return $this->render('place/new.html.twig', ['place' => $place, 'form' => $form]);
    }

    /** Szczegóły miejsca to jego karta na ekranie startowym. */
    #[Route('/{id}', name: 'place_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Place $place): Response
    {
        return $this->redirectToRoute('place_index', ['place' => $place->getId()]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}/edit', name: 'place_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Place $place, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PlaceType::class, $place);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('place_index', ['place' => $place->getId()]);
        }

        return $this->render('place/edit.html.twig', ['place' => $place, 'form' => $form]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}', name: 'place_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Place $place, EntityManagerInterface $entityManager): Response
    {
        if ($place->getGames()->count() > 0) {
            $this->addFlash('danger', 'Nie można usunąć miejsca, w którym są gry. Najpierw usuń jego gry.');

            return $this->redirectToRoute('place_index', ['place' => $place->getId()]);
        }
        if ($this->isCsrfTokenValid('delete'.$place->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($place);
            $entityManager->flush();
        }

        return $this->redirectToRoute('place_index');
    }
}
