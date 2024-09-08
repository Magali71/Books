<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class BookController extends AbstractController
{
    #[Route('/api/books', name: 'app_book', methods: ['GET'])]
    public function getAllBooks(
        BookRepository $bookRepository,
        SerializerInterface $serializer,
        Request $request,
        TagAwareCacheInterface $cachePool
    ): JsonResponse
    {
        // pagination, le 1 et le 3 sont des paramètres par defaut s'ils ne sont pas def dans l'url
        $page = $request->get('page', 1);
        $nbItem = $request->get('limit', 3);

        // premiere étape : récupérer les données souhaitées
        // l'exemple ici est tout simple et répucère tous les livres (sans mise en place de la pagination)
        // $books = $bookRepository->findAll();

        // Création d'un id de cache pour identifier ce qui est en cache
        $idCache = "getAllBooks-" . $page . "-" . $nbItem;

        // récupération de tous les livres avec la pagination seule
        // $books = $bookRepository->findAllWithPagination($page, $nbItem);

        // mise en place du cache
        $jsonBookList = $cachePool->get($idCache, function (ItemInterface $item) use ($bookRepository, $page, $nbItem, $serializer) {
            $item->tag("booksCache");
            $booksList = $bookRepository->findAllWithPagination($page, $nbItem);
            // deuxième étape : convertir les données en json
            return $serializer->serialize($booksList, 'json', ['groups' => 'getBooks']);
        });

        // true est important il permet de dire que les données sont déjà en json et de les afficher correctement
        // le [] correspond aux headers
        return new JsonResponse(
            $jsonBookList, Response::HTTP_OK, [], true
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

    /**
     * @param Book $book
     * @param EntityManagerInterface $em
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse
     * @throws \Psr\Cache\InvalidArgumentException
     */
    #[Route('/api/books/{id}', name: 'deleteBook', methods: ['DELETE'])]
    public function deleteBook(Book $book, EntityManagerInterface $em, TagAwareCacheInterface $cachePool): JsonResponse
    {
        // vider le cache quand on supprime un élément
        $cachePool->invalidateTags(["booksCache"]);
        $em->remove($book);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/books', name: 'detail_book', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un livre')]
    public function createBook(Request $request, SerializerInterface $serializer,
       EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator,
       AuthorRepository $authorRepository, ValidatorInterface $validator): JsonResponse
    {
        // on récupère ce que l'on envoie (du json) = $request->getContent();
        // on passe d'un json à un Objet Book pour pouvoir l'enregistrer en base de données
        $book = $serializer->deserialize($request->getContent(), Book::class, 'json');

        // on valide qu'il n'y ai pas d'erreurs
        $errors = $validator->validate($book);

        if ($errors->count() > 0) {
           return new JsonResponse($serializer->serialize($errors, 'json'),
                Response::HTTP_BAD_REQUEST, [], true);
            // ou throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, "La requête est invalide");
        }

        // POUR RECUPERER L'ID DE L'AUTEUR
        // 1. Récupération de l'ensemble des données envoyées sous forme de tableau
        $contentArray = $request->toArray();
        // 2. Récupération de l'idAuthor. S'il n'est pas défini, alors on met -1 par défaut.
        $idAuthor = $contentArray['idAuthor'] ?? -1;

        // On cherche l'auteur qui correspond et on l'assigne au livre.
        // Si "find" ne trouve pas l'auteur, alors null sera retourné.
        $book->setAuthor($authorRepository->find($idAuthor));

        $em->persist($book);
        $em->flush();

        // je mets l'Objet crée en json pour le retourner dans la réponse
        $jsonBook = $serializer->serialize($book, 'json', ['groups' => 'getBooks']);

        // on va envoyer dans les hearders la location cad l'Url du nouveau livre créé
        $location =  $urlGenerator->generate('detailBook', ['id' => $book->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonBook, Response::HTTP_CREATED, ['Location' => $location], true);
    }

    // on va utiliser un paramConverteur
    #[Route('/api/books/{id}', name: 'updateBook', methods: ['PUT'])]
    public function updateBook(Request $request, SerializerInterface $serializer,
    Book $currentBook, AuthorRepository $authorRepository, EntityManagerInterface $em)
    {
        $updatedBook = $serializer->deserialize($request->getContent(),  Book::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $currentBook]);

        $contentArray = $request->toArray();
        $idAuthor = $contentArray['idAuthor'] ?? -1;
        $author = $authorRepository->find($idAuthor);
        $updatedBook->setAuthor($author);

        $em->persist($updatedBook);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
