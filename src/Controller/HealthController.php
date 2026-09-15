<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\FileReadingStore;

final class HealthController extends AbstractController
{
    public function __construct(
        private readonly FileReadingStore $readingStore,
    ) {

    }
    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function index(): JsonResponse
    {
        if (!$this->readingStore->isReady()) {
            return $this->json(
                ['status' => 'unavailable'],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }
        return $this->json([
            'status' => 'OK',
        ]);
    }
}
