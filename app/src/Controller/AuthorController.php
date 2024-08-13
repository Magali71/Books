<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthorController extends AbstractController
{
    /**
     * Cette méthode permet de récupérer l'ensemble des auteurs.
     *
     * @param AuthorRepository $authorRepository
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('/api/authors', name: 'authors', methods: ['GET'])]
    public function getAllAuthors(AuthorRepository $authorRepository, SerializerInterface $serializer): JsonResponse
    {
        $authors= $authorRepository->findAll();

        $jsonAuthors = $serializer->serialize($authors, 'json', ['groups' => 'getAuthors']);

        return new JsonResponse($jsonAuthors, Response::HTTP_OK, [], true);
    }

    /**
     * Cette méthode permet de récupérer un auteur en particulier en fonction de son id.
     *
     * @param Author $author
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    // méthode avec un paramConverter
    #[Route('/api/authors/{id}', name: 'detailAuthor', methods: ['GET'])]
    public function getDetailAuthor(Author $author, SerializerInterface $serializer) {
        $jsonAuthor = $serializer->serialize($author, 'json', ['groups' => 'getAuthors']);

        return new JsonResponse($jsonAuthor, Response::HTTP_OK, [], true);
    }

    /**
     * Cette méthode supprime un auteur en fonction de son id.
     * il y a une action en base de données donc on a besoin d'entityManager
     * utilisation des paramConverteur
     * En cascade, les livres associés aux auteurs seront aux aussi supprimés.
     *
     * /!\ Attention /!\
     * pour éviter le problème :
     * "1451 Cannot delete or update a parent row: a foreign key constraint fails"
     * Il faut bien penser rajouter dans l'entité Book, au niveau de l'author :
     * #[ORM\JoinColumn(onDelete:"CASCADE")]
     *
     * Et resynchronizer la base de données pour appliquer ces modifications.
     * avec : php bin/console doctrine:schema:update --force
     *
     * @param Author $author
     * @param EntityManagerInterface $em
     * @return JsonResponse
     */
    #[Route('api/authors/{id}', name: 'deleteAuthor', methods:['DELETE'])]
    public function deleteAuthor(Author $author, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($author);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * il y a une action en base de données donc on a besoin d'entityManager
     * Cette méthode permet de créer un nouvel auteur. Elle ne permet pas
     * d'associer directement des livres à cet auteur.
     * Exemple de données :
     * {
     * "lastName": "Tolkien",
     * "firstName": "J.R.R"
     * }
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param UrlGeneratorInterface $urlGenerator
     * @return JsonResponse
     */
    #[Route('/api/authors', name: 'createAuthors', methods: ['POST'])]
    public function createAuthor(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        UrlGeneratorInterface $urlGenerator,
        ValidatorInterface $validator
    ): JsonResponse {
        $author = $serializer->deserialize($request->getContent(),Author::class, 'json');

        $errors = $validator->validate($author);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'),
                Response::HTTP_BAD_REQUEST, [], true);
        }

        $em->persist($author);
        $em->flush();

        $location = $urlGenerator->generate('detailAuthor', ['id' => $author->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $authorJson = $serializer->serialize($author, 'json');

        return new JsonResponse($authorJson, Response::HTTP_CREATED, ['Location' => $location], true);
    }

    /**
     * il y a une action à faire en base de données donc il faut faire appel à l'entity manager
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param Author $currentAuthor
     * @return JsonResponse
     */
    #[Route('/api/authors/{id}', name: 'updateAuthor', methods: ['PUT'])]
    public function updateAuthor(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        Author $currentAuthor
        ): JsonResponse {
        $authorUpdated = $serializer->deserialize($request->getContent(), Author::class, 'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $currentAuthor]);

        $em->persist($authorUpdated);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}