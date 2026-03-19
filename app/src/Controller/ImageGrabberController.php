<?php

namespace App\Controller;

use App\Service\ImageGrabberService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ImageGrabberController extends AbstractController
{
    #[Route('/', name: 'app_index', methods: ['GET'])]
    public function index(ImageGrabberService $service): Response
    {
        $images = $service->getSavedImages();
        return $this->render('index.html.twig', ['images' => $images]);
    }

    #[Route('/grab', name: 'app_grab', methods: ['POST'])]
    public function grab(Request $request, ImageGrabberService $service): JsonResponse
    {
        $url       = trim($request->request->get('url', ''));
        $minWidth  = (int) $request->request->get('min_width', 0);
        $minHeight = (int) $request->request->get('min_height', 0);
        $text      = trim($request->request->get('text', ''));

        if (empty($url)) {
            return $this->json(['success' => false, 'error' => 'URL не указан'], 400);
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->json(['success' => false, 'error' => 'Некорректный URL'], 400);
        }

        try {
            $images = $service->grabImages($url, $minWidth, $minHeight, $text);
            return $this->json(['success' => true, 'images' => $images, 'count' => count($images)]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
