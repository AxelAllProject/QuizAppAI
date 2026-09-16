<?php

namespace App\Quiz\UI\Http\Controller;

use App\Quiz\Domain\Model\QuizImage;
use App\Quiz\Infrastructure\Storage\ImageUploader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;

/** Route de téléversement des images de quiz. */
class UploadController extends AbstractController
{
    public function __construct(private readonly ImageUploader $uploader)
    {
    }

    /** Téléversement d'une illustration (couverture ou question), avant l'enregistrement du quiz. */
    #[Route('/api/uploads', name: 'api_upload', methods: ['POST'])]
    #[IsGranted('ROLE_TEACHER', message: 'Il faut être professeur ou administrateur pour ajouter une image.')]
    public function upload(
        #[MapUploadedFile([
            new Assert\Image(
                maxSize: '5M',
                mimeTypes: QuizImage::MIME_TYPES,
                maxSizeMessage: 'L’image dépasse {{ limit }} {{ suffix }}.',
                mimeTypesMessage: 'Formats acceptés : JPEG, PNG, GIF ou WebP.',
            ),
        ])]
        UploadedFile $image,
    ): JsonResponse {
        return $this->json(['path' => $this->uploader->store($image)], 201);
    }
}
