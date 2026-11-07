<?php

namespace App\Controller;

use App\Entity\Place;
use App\Entity\PlaceMember;
use App\Entity\User;
use App\Form\PlaceType;
use App\Repository\AttendanceRepository;
use App\Repository\GameRepository;
use App\Repository\PaymentRepository;
use App\Repository\PlaceMemberRepository;
use App\Repository\PlaceRepository;
use App\Security\PlaceVoter;
use App\Service\BalanceCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/place')]
final class PlaceController extends AbstractController
{
    /** Ekran startowy: MOJE miejsca jako przewijane karty (strzałki, klawiatura, przesunięcie palcem). */
    #[Route('/', name: 'place_index', methods: ['GET'])]
    public function index(
        Request $request,
        PlaceMemberRepository $members,
        GameRepository $gameRepository,
        AttendanceRepository $attendanceRepository,
        BalanceCalculator $balanceCalculator,
    ): Response {
        $me = $this->currentUser();
        $presentCounts = $attendanceRepository->presentCountsByGame();

        $cards = [];
        $activeIndex = 0;
        $wanted = (int) filter_var($request->query->all()['place'] ?? null, FILTER_VALIDATE_INT);
        foreach ($members->placesOf($me) as $i => $place) {
            if ($place->getId() === $wanted) {
                $activeIndex = $i;
            }
            $cards[] = [
                'place' => $place,
                'games' => $gameRepository->latestForPlace($place, 5),
                'gamesTotal' => $place->getGames()->count(),
                'people' => $attendanceRepository->distinctAttendeesForPlace($place),
                'members' => \count($place->getMembers()),
                'balance' => $balanceCalculator->forUserInPlace($me, $place),
            ];
        }

        return $this->render('place/index.html.twig', [
            'cards' => $cards,
            'activeIndex' => $activeIndex,
            'presentCounts' => $presentCounts,
        ]);
    }

    /** Wyszukiwarka miejsc: uczestnik znajduje miejsce po nazwie lub mieście i do niego dołącza. */
    #[Route('/search', name: 'place_search', methods: ['GET'])]
    public function search(Request $request, PlaceRepository $placeRepository, PlaceMemberRepository $members): Response
    {
        $me = $this->currentUser();
        $raw = $request->query->all()['q'] ?? '';
        $query = mb_substr(trim(\is_string($raw) ? $raw : ''), 0, 100);
        $found = $placeRepository->search($query, 31);
        $truncated = \count($found) > 30;
        $found = \array_slice($found, 0, 30);

        $mine = [];
        foreach ($members->placesOf($me) as $place) {
            $mine[$place->getId()] = true;
        }

        return $this->render('place/search.html.twig', [
            'q' => $query,
            'places' => $found,
            'mine' => $mine,
            'truncated' => $truncated,
        ]);
    }

    #[Route('/{id}/join', name: 'place_join', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function join(Request $request, Place $place, PlaceMemberRepository $members, EntityManagerInterface $entityManager): Response
    {
        $me = $this->currentUser();
        if (!$this->isCsrfTokenValid('join'.$place->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Nieprawidłowy token formularza. Odśwież stronę i spróbuj ponownie.');

            return $this->redirectToRoute('place_search');
        }
        if (!$members->isMember($place, $me)) {
            $entityManager->persist((new PlaceMember())->setPlace($place)->setUser($me));
            $entityManager->flush();
            $this->addFlash('success', sprintf('Dołączyłeś do miejsca „%s”.', $place->getName()));
        }

        return $this->redirectToRoute('place_index', ['place' => $place->getId()]);
    }

    #[Route('/{id}/leave', name: 'place_leave', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function leave(Request $request, Place $place, PlaceMemberRepository $members, EntityManagerInterface $entityManager): Response
    {
        $me = $this->currentUser();
        $membership = $members->findOneFor($place, $me);
        if (null === $membership || !$this->isCsrfTokenValid('leave'.$place->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('place_index');
        }
        if ($membership->isOrganizer() && $members->organizerCount($place) < 2) {
            $this->addFlash('danger', 'Jesteś jedynym organizatorem tego miejsca. Najpierw mianuj kogoś innego organizatorem.');

            return $this->redirectToRoute('place_members', ['id' => $place->getId()]);
        }
        $entityManager->remove($membership);
        $entityManager->flush();
        $this->addFlash('success', sprintf('Opuściłeś miejsce „%s”. Twoje obecności i wpłaty zostają w historii.', $place->getName()));

        return $this->redirectToRoute('place_index');
    }

    /** Miejsce zakłada organizator (rola globalna) i od razu zostaje organizatorem tego miejsca. */
    #[IsGranted('ROLE_ORGANIZER')]
    #[Route('/new', name: 'place_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $place = new Place();
        $form = $this->createForm(PlaceType::class, $place);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($place);
            $entityManager->persist((new PlaceMember())->setPlace($place)->setUser($this->currentUser())->setOrganizer(true));
            $entityManager->flush();

            return $this->redirectToRoute('place_index', ['place' => $place->getId()]);
        }

        return $this->render('place/new.html.twig', ['place' => $place, 'form' => $form]);
    }

    /** Szczegóły miejsca to jego karta na ekranie startowym. */
    #[Route('/{id}', name: 'place_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(PlaceVoter::VIEW, subject: 'place')]
    public function show(Place $place): Response
    {
        return $this->redirectToRoute('place_index', ['place' => $place->getId()]);
    }

    #[Route('/{id}/edit', name: 'place_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(PlaceVoter::MANAGE, subject: 'place')]
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

    /** Zarządzanie uczestnikami: nadawanie roli organizatora i usuwanie z miejsca. */
    #[Route('/{id}/members', name: 'place_members', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(PlaceVoter::MANAGE, subject: 'place')]
    public function members(Place $place, PlaceMemberRepository $members): Response
    {
        return $this->render('place/members.html.twig', [
            'place' => $place,
            'members' => $members->membersOf($place),
            'organizers' => $members->organizerCount($place),
        ]);
    }

    #[Route('/{id}/members/{memberId}/organizer', name: 'place_member_organizer', methods: ['POST'], requirements: ['id' => '\d+', 'memberId' => '\d+'])]
    #[IsGranted(PlaceVoter::MANAGE, subject: 'place')]
    public function toggleOrganizer(
        Request $request,
        Place $place,
        #[MapEntity(id: 'memberId')] PlaceMember $member,
        PlaceMemberRepository $members,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($member->getPlace() !== $place) {
            throw $this->createNotFoundException();
        }
        if ($this->isCsrfTokenValid('member'.$member->getId(), (string) $request->request->get('_token'))) {
            if ($member->isOrganizer() && $members->organizerCount($place) < 2) {
                $this->addFlash('danger', 'W miejscu musi zostać co najmniej jeden organizator.');
            } else {
                $member->setOrganizer(!$member->isOrganizer());
                $entityManager->flush();
                $this->addFlash('success', 'Zmieniono rolę.');
            }
        }

        return $this->redirectToRoute('place_members', ['id' => $place->getId()]);
    }

    #[Route('/{id}/members/{memberId}/remove', name: 'place_member_remove', methods: ['POST'], requirements: ['id' => '\d+', 'memberId' => '\d+'])]
    #[IsGranted(PlaceVoter::MANAGE, subject: 'place')]
    public function removeMember(
        Request $request,
        Place $place,
        #[MapEntity(id: 'memberId')] PlaceMember $member,
        PlaceMemberRepository $members,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($member->getPlace() !== $place) {
            throw $this->createNotFoundException();
        }
        if ($this->isCsrfTokenValid('member'.$member->getId(), (string) $request->request->get('_token'))) {
            if ($member->isOrganizer() && $members->organizerCount($place) < 2) {
                $this->addFlash('danger', 'W miejscu musi zostać co najmniej jeden organizator.');
            } else {
                $entityManager->remove($member);
                $entityManager->flush();
                $this->addFlash('success', 'Osoba usunięta z miejsca. Jej obecności i wpłaty zostają w historii.');
            }
        }

        return $this->redirectToRoute('place_members', ['id' => $place->getId()]);
    }

    #[Route('/{id}', name: 'place_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(PlaceVoter::MANAGE, subject: 'place')]
    public function delete(Request $request, Place $place, PaymentRepository $payments, EntityManagerInterface $entityManager): Response
    {
        if ($place->getGames()->count() > 0 || $payments->count(['place' => $place]) > 0) {
            $this->addFlash('danger', 'Nie można usunąć miejsca, w którym są gry lub wpłaty. Najpierw je usuń.');

            return $this->redirectToRoute('place_index', ['place' => $place->getId()]);
        }
        if ($this->isCsrfTokenValid('delete'.$place->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($place);
            $entityManager->flush();
        }

        return $this->redirectToRoute('place_index');
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
