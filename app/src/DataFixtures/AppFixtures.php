<?php

namespace App\DataFixtures;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private $userPasswordHasher;
    // récupération du service qui permet d'encoder les mots de passe
    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->userPasswordHasher = $userPasswordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // creation d'un user simple
        $user = new User();
        $user->setEmail('user@bookapi.com');
        $user->setRoles(["ROLE_USER"]);
        $user->setPassword($this->userPasswordHasher->hashPassword($user, "password"));
        $manager->persist($user);

        // création d'un user admin
        $userAdmin = new User();
        $userAdmin->setEmail('admin@bookapi.com');
        $userAdmin->setRoles(["ROLE_ADMIN"]);
        $userAdmin->setPassword($this->userPasswordHasher->hashPassword($userAdmin, "password"));
        $manager->persist($userAdmin);

        // création des auteurs
        $authors = [];
        for ($i=0; $i<20; $i++) {
            $author = (new Author())
                ->setFirstName("Prénom " . $i)
                ->setLastName("Nom " . $i);
            $manager->persist($author);
            $authors[] = $author;
        }

        // création des livres
        for ($i=0; $i<20; $i++) {
            $book = new Book();
            $book->setTitle('Titre : '. $i);
            $book->setCoverText('La quatrième de couverture numéro : ' . $i);
            // on lie le livre à un auteur pris au hasard
            $book->setAuthor($authors[array_rand($authors)]);
            $book->setComment("Commentaire du bibliothécaire " . $i);
            $manager->persist($book);
        }

        $manager->flush();
    }
}
