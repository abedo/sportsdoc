<?php

namespace App\Controller;

use App\Entity\Attendance;
use App\Entity\Game;
use App\Entity\Place;
use App\Entity\User;
use App\Form\GameCreateType;
use App\Form\GameType;
use App\Repository\AttendanceRepository;
use App\Repository\GameRepository;
use App\Repository\PlaceMemberRepository;
use App\Repository\PlaceRepository;
use App\Security\PlaceVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/game')]
final class GameController extends AbstractController
{
    #[Route('/', name: 'game_index', methods: ['GET'])]
    public function index(GameRepository $gameRepository, AttendanceRepository $attendanceRepository, PlaceMemberRepository $members): Response
    {
        $me = $this->getUser();

        return $this->render('game/index.html.twig', [
            'games' => $me instanceof User ? $gameRepository->latestForPlaces($members->placesOf($me)) : [],
            'presentCounts' => $attendanceRepository->presentCountsByGame(),
        ]);
    }

    /** Krok 1 flow organizatora: nowa gra z karty miejsca, na razie tylko data. */
    #[Route('/new', name: 'game_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, PlaceRepository $placeRepository, GameRepository $gameRepository): Response
    {
        $place = $placeRepository->find((int) filter_var($request->query->all()['place'] ?? null, FILTER_VALIDATE_INT));
        if (!$place instanceof Place) {
            $this->addFlash('warning', 'Wybierz miejsce, w którym ma się odbyć gra.');

            return $this->redirectToRoute('place_index');
        }
        $this->denyAccessUnlessGranted(PlaceVoter::MANAGE, $place);

        $game = (new Game())->setPlace($place)->setCost($gameRepository->lastCostForPlace($place));
        $form = $this->createForm(GameCreateType::class, $game);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($game);
            $entityManager->flush();
            $this->addFlash('success', 'Gra dodana. Ustaw koszt i zaznacz obecnych.');

            return $this->redirectToRoute('game_edit', ['id' => $game->getId()]);
        }

        return $this->render('game/new.html.twig', ['game' => $game, 'place' => $place, 'form' => $form]);
    }

    /** Widok dla wszystkich: szczegóły, kto był, a zwykła osoba zaznacza własną obecność. */
    #[Route('/{id}', name: 'game_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(PlaceVoter::VIEW, subject: 'game')]
    public function show(Game $game, AttendanceRepository $attendanceRepository): Response
    {
        $presence = $attendanceRepository->presenceMapForGame($game);
        $me = $this->getUser();

        $present = array_map(
            static fn (Attendance $a): User => $a->getUser(),
            $attendanceRepository->findBy(['game' => $game, 'present' => true]),
        );
        usort($present, static fn (User $a, User $b): int => strcasecmp((string) $a->getFullname(), (string) $b->getFullname()));

        return $this->render('game/show.html.twig', [
            'game' => $game,
            'presentUsers' => $present,
            'myPresence' => $me instanceof User ? ($presence[$me->getId()] ?? null) : null,
        ]);
    }

    /** Krok 2 flow organizatora: edycja gry i lista obecności. */
    #[IsGranted(PlaceVoter::MANAGE, subject: 'game')]
    #[Route('/{id}/edit', name: 'game_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        Game $game,
        EntityManagerInterface $entityManager,
        PlaceMemberRepository $members,
        AttendanceRepository $attendanceRepository,
    ): Response {
        $form = $this->createForm(GameType::class, $game);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Zmiany zapisane.');

            return $this->redirectToRoute('game_edit', ['id' => $game->getId()]);
        }

        $presence = $attendanceRepository->presenceMapForGame($game);

        return $this->render('game/edit.html.twig', [
            'game' => $game,
            'form' => $form,
            'users' => $members->usersOf($game->getPlace()),
            'presence' => $presence,
            'presentCount' => count(array_filter($presence)),
        ]);
    }

    /** Organizator zapisuje listę obecności wszystkich: zaznaczeni „byli”, pozostali „nie byli”. */
    #[IsGranted(PlaceVoter::MANAGE, subject: 'game')]
    #[Route('/{id}/attendance', name: 'game_attendance', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function saveAttendance(
        Request $request,
        Game $game,
        PlaceMemberRepository $members,
        AttendanceRepository $attendanceRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('attendance'.$game->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Nieprawidłowy token formularza. Odśwież stronę i spróbuj ponownie.');

            return $this->redirectToRoute('game_edit', ['id' => $game->getId()]);
        }

        $presentIds = array_map('intval', (array) $request->request->all('present'));
        $existing = [];
        foreach ($attendanceRepository->findBy(['game' => $game]) as $attendance) {
            $existing[(int) $attendance->getUser()?->getId()] = $attendance;
        }

        foreach ($members->usersOf($game->getPlace()) as $user) {
            $attendance = $existing[$user->getId()] ?? (new Attendance())->setGame($game)->setUser($user);
            $attendance->setPresent(in_array($user->getId(), $presentIds, true));
            $entityManager->persist($attendance);
        }
        $entityManager->flush();

        $this->addFlash('success', 'Lista obecności zapisana.');

        return $this->redirectToRoute('game_edit', ['id' => $game->getId()]);
    }

    /** Zwykła osoba zaznacza wyłącznie własną obecność („byłem” / „nie byłem”). */
    #[Route('/{id}/me', name: 'game_attendance_me', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(PlaceVoter::VIEW, subject: 'game')]
    public function saveMyAttendance(
        Request $request,
        Game $game,
        AttendanceRepository $attendanceRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $me = $this->getUser();
        if (!$me instanceof User) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('attendance-me'.$game->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Nieprawidłowy token formularza. Odśwież stronę i spróbuj ponownie.');

            return $this->redirectToRoute('game_show', ['id' => $game->getId()]);
        }

        $attendance = $attendanceRepository->findOneFor($game, $me) ?? (new Attendance())->setGame($game)->setUser($me);
        $attendance->setPresent('1' === $request->request->get('present'));
        $entityManager->persist($attendance);
        $entityManager->flush();

        $this->addFlash('success', $attendance->isPresent() ? 'Zapisano: byłeś na tej grze.' : 'Zapisano: nie byłeś na tej grze.');

        return $this->redirectToRoute('game_show', ['id' => $game->getId()]);
    }

    #[IsGranted(PlaceVoter::MANAGE, subject: 'game')]
    #[Route('/{id}', name: 'game_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Game $game, EntityManagerInterface $entityManager): Response
    {
        $placeId = $game->getPlace()?->getId();
        if ($this->isCsrfTokenValid('delete'.$game->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($game);
            $entityManager->flush();
        }

        return $this->redirectToRoute('place_index', ['place' => $placeId]);
    }
}
