<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class AuthorController extends AbstractController
{
    #[Route('/api/authors', name: 'authors', methods: ['GET'])]
    public function getAllAuthors(AuthorRepository $authorRepository, SerializerInterface $serializer): JsonResponse
    {
        $authors= $authorRepository->findAll();

        $jsonAuthors = $serializer->serialize($authors, 'json', ['groups' => 'getAuthors']);

        return new JsonResponse($jsonAuthors, Response::HTTP_OK, [], true);
    }

    // méthode avec un paramConverter
    #[Route('/api/authors/{id}', name: 'detailAuthor', methods: ['GET'])]
    public function getDetailAuthor(Author $author, SerializerInterface $serializer) {
        $jsonAuthor = $serializer->serialize($author, 'json', ['groups' => 'getAuthors']);

        return new JsonResponse($jsonAuthor, Response::HTTP_OK, [], true);
    }
}