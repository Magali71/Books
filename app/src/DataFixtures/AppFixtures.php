<?php

namespace App\DataFixtures;

use App\Entity\Author;
use App\Entity\Book;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
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
            $manager->persist($book);
        }

        $manager->flush();
    }
}
