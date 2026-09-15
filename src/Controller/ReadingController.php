<?php

namespace App\Controller;

use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\FileReadingStore;
use App\Service\CriticalityEvaluator;

final class ReadingController extends AbstractController
{
    private const ALLOWED_TYPES = [
        'temperature',
        'heart_rate',
        'oxygen_saturation',
    ];

    public function __construct(
        private readonly FileReadingStore $readingStore,
        private readonly CriticalityEvaluator $criticalityEvaluator
    ) {
    }

    /**
     * @throws RandomException
     */
    #[Route('/devices/{deviceId}/readings', name: 'readings_create', methods: ['POST'])]
    public function create(Request $request, string $deviceId): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json(['error' => 'Le corps doit être du JSON valide'], Response::HTTP_BAD_REQUEST);
        }

        if (!is_array($data)) {
            return $this->json(['error' => 'La structure JSON est invalide'], Response::HTTP_BAD_REQUEST);
        }

        $validationErrors = $this->validateReadingData($data);
        if ($validationErrors !== null) {
            return $this->json(['errors' => $validationErrors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $value = $data['value'] + 0;

        $reading = [
            'id' => bin2hex(random_bytes(8)),
            'device_id' => $deviceId,
            'type' => $data['type'],
            'value' => $value,
            'critical' => $this->criticalityEvaluator->isCritical($data['type'], $value),
            'timestamp' => $data['timestamp'],
        ];

        $this->readingStore->add($reading);
        return $this->json($reading, Response::HTTP_CREATED);
    }

    #[Route('/devices/{deviceId}/readings', name: 'readings_list', methods: ['GET'])]
    public function list(string $deviceId): JsonResponse
    {
        $readings = $this->readingStore->listByDevice($deviceId);
        return $this->json($readings);
    }

    #[Route('/devices/{deviceId}/readings/{readingId}', name: 'readings_delete', methods: ['DELETE'])]
    public function delete(string $deviceId, string $readingId): JsonResponse
    {
        $deleted = $this->readingStore->deleteByDeviceAndId($deviceId, $readingId);
        if (!$deleted) {
            return $this->json(['error' => 'Mesure non trouvée'], Response::HTTP_NOT_FOUND);
        }
        return $this->json(['message' => 'Mesure supprimée avec succès']);
    }

    private function validateReadingData(array $data): ?array
    {
        $errors = [];

        if (!isset($data['type'])
            || !is_string($data['type'])
            || !in_array($data['type'], self::ALLOWED_TYPES, true)
            )
        {
            $errors['type'] = 'Le type de mesure est requis et doit être l\'un des suivants : ' . implode(', ', self::ALLOWED_TYPES);
        }

        if (!isset($data['value'])
            || !is_numeric($data['value'])
            || !is_finite((float) $data['value'])
        ) {
            $errors['value'] = 'La valeur de la mesure est requise et doit être un nombre.';
        }

        if (!isset($data['timestamp']) || !is_string($data['timestamp']))
        {
            $errors['timestamp'] = 'Le timestamp est obligatoire.';
        } else {
            try {
                new \DateTimeImmutable($data['timestamp']);
            } catch (\Exception) {
                $errors['timestamp'] = 'Le timestamp doit être une date valide.';
            }
        }

        return empty($errors) ? null : $errors;
    }
}
