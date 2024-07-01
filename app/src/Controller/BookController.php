<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class BookController extends AbstractController
{
    #[Route('/api/books', name: 'app_book', methods: ['GET'])]
    public function getAllBooks(BookRepository $bookRepository, SerializerInterface $serializer): JsonResponse
    {
        // premiere étape : récupérer les données souhaitées
        $books = $bookRepository->findAll();
        // deuxième étape : convertir les données en json
        $jsonBooks = $serializer->serialize($books, 'json', ['groups' => 'getBooks']);

        // true est important il permet de dire que les données sont déjà en json et de les afficher correctement
        // le [] correspond aux headers
        return new JsonResponse(
            $jsonBooks, Response::HTTP_OK, [], true
        );
    }

    #[Route('/api/books/{id}', name: 'detail_book', methods: ['GET'])]
    public function getDetailBook(BookRepository $bookRepository, SerializerInterface $serializer, int $id): JsonResponse
    {
        // premiere étape : récupérer les données souhaitées
        $book = $bookRepository->find($id);
        if ($book) {
            // deuxième étape : convertir les données en json
            $bookJson = $serializer->serialize($book, 'json', ['groups' => 'getBooks']);
            // true est important il permet de dire que les données sont déjà en json et de les afficher correctement
            // le [] correspond aux headers
            return new JsonResponse(
                $bookJson, Response::HTTP_OK, [], true);
        }

        return new JsonResponse('pas de livre', Response::HTTP_NOT_FOUND);
    }

    // autre façon de faire pour récupérer un book avec un paramConverteur :
    // #[Route('/api/books/{id}', name: 'detail_book', methods: ['GET'])]
    //public function getDetailBook(Book $book, SerializerInterface $serializer): JsonResponse
    //{
       //$bookJson = $serializer->serialize($book, 'json');

        //return new JsonResponse($bookJson, Response::HTTP_OK, ['accept' => 'json'], true);
    //}


    #[Route('/api/books/{id}', name: 'detail_book', methods: ['DELETE'])]
    public function deleteBook(Book $book, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($book);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/books', name: 'detail_book', methods: ['POST'])]
    public function createBook(Request $request, SerializerInterface $serializer,EntityManagerInterface $em): JsonResponse
    {
        $request = $request->getContent();
        $book = $serializer->deserialize($request, Book::class, 'json');
        //todo verifier le type de $book
        $em->persist($book);
        $em->flush();

        return new JsonResponse($book, Response::HTTP_CREATED, [], true);
    }
}
