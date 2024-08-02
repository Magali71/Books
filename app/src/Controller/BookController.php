<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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

    #[Route('/api/books/{id}', name: 'detailBook', methods: ['GET'])]
    public function getDetailBook(BookRepository $bookRepository, SerializerInterface $serializer, int $id): JsonResponse
    {
        // premiere étape : récupérer les données souhaitées
        $book = $bookRepository->find($id);
        if ($book) {
            // deuxième étape : convertir les données en json
            $bookJson = $serializer->serialize($book, 'json', ['groups' => 'getBooks']);
            // true est important il permet de dire que les données sont déjà en json et de les afficher correctement
            // le [] correspond aux headers => on peut envoyer des infos dans les headers
            return new JsonResponse(
                $bookJson, Response::HTTP_OK, [], true);
        }

        return new JsonResponse('pas de livre', Response::HTTP_NOT_FOUND);
    }

    // autre façon de faire pour récupérer un book avec un paramConverteur
    // permet de ne pas passer par le repo :
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
    public function createBook(Request $request, SerializerInterface $serializer,
       EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator,
       AuthorRepository $authorRepository): JsonResponse
    {
        // on récupère ce que l'on envoie (du json) = $request->getContent();
        // on passe d'un json à un Objet Book pour pouvoir l'enregistrer en base de données
        $book = $serializer->deserialize($request->getContent(), Book::class, 'json');

        // POUR RECUPERER L'IDDE L'AUTEUR
        // 1. Récupération de l'ensemble des données envoyées sous forme de tableau
        $content = $request->toArray();
        // 2. Récupération de l'idAuthor. S'il n'est pas défini, alors on met -1 par défaut.
        $idAuthor = $content['idAuthor'] ?? -1;

        // On cherche l'auteur qui correspond et on l'assigne au livre.
        // Si "find" ne trouve pas l'auteur, alors null sera retourné.
        $book->setAuthor($authorRepository->find($idAuthor));

        $em->persist($book);
        $em->flush();

        // je mets l'Objet crée en json pour le retourner dans la réponse
        $jsonBook = $serializer->serialize($book, 'json', ['groups' => 'getBooks']);

        // on va envoyer dans les hearders la location cad l'Url du nouveau livre créé
        $location =  $urlGenerator->generate('detailBook', ['id' => $book->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonBook, Response::HTTP_CREATED, ['location' => $location], true);
    }
}
