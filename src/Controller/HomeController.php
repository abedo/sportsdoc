<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || null === $user->getAppRole()) {
            return $this->redirectToRoute('choose_role');
        }

        // Organizator zaczyna od miejsc, uczestnik od gier.
        return $this->redirectToRoute($user->isOrganizer() ? 'place_index' : 'game_index');
    }
}
