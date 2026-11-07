<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Strony wymagane przez Google i Facebooka: polityka prywatności i instrukcja usuwania danych. Publiczne. */
final class LegalController extends AbstractController
{
    #[Route('/privacy', name: 'privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('legal/privacy.html.twig');
    }

    #[Route('/data-deletion', name: 'data_deletion', methods: ['GET'])]
    public function dataDeletion(): Response
    {
        return $this->render('legal/data_deletion.html.twig');
    }
}
