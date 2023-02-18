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
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BookController extends AbstractController
{
    #[Route('/api/books', name: 'app_book', methods: ['GET'])]

    public function getAllBooks(BookRepository $bookRepository, SerializerInterface $serializer): JsonResponse
    {
        $bookList = $bookRepository->findAll();
        $jsonBookList = $serializer->serialize($bookList, 'json', ['groups' => 'getBooks']);
        return new JsonResponse($jsonBookList, Response::HTTP_OK, [], true);
    }

    #[Route('api/books/{id}', name: 'detailBook', methods: ['GET'])]

    public function getDetailBook(int $id, SerializerInterface $serializer, BookRepository $bookRepository): JsonResponse
    {
        $book = $bookRepository->find($id);
        if ($book) {
            $jsonBook = $serializer->serialize($book, 'json', ['groups' => 'getBooks']);
            return new JsonResponse($jsonBook, Response::HTTP_OK, [], true);
        }
        return new JsonResponse(['message' => 'Ressource introuvable'], Response::HTTP_NOT_FOUND);
    }

    #[Route('api/books/{id}', name: 'deleteBook', methods: ['DELETE'])]

    public function deleteBook(int $id, BookRepository $bookRepository, EntityManagerInterface $em): JsonResponse
    {
        $book = $bookRepository->find($id);
        $em->remove($book);
        $em->flush();
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/books', name: 'createBook', methods: ['POST'])]
    
    public function createBook(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, AuthorRepository $authorRepository, ValidatorInterface $validator): JsonResponse
    {
        // if not Admin, throw an exception
        $this->denyAccessUnlessGranted('ROLE_ADMIN', null, "Vous n'avez pas les autorisations nécessaires pour ajouter un livre");
        $book = $serializer->deserialize($request->getContent(), Book::class, 'json');
        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;
        // l'opérateur ?? permet de gérer l'existence ou non d'une valeur. EX: Si l'idAuteur n'est pas renseigné, $idAuthor = -1
        // Si l'id est -1, (Author)->find(-1) renverra null
        $book->setAuthor($authorRepository->find($idAuthor));
        $errors = $validator->validate($book);
        if($errors->count() > 0){
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST,[],true);
        }
        $em->persist($book);
        $em->flush();
        $jsonBook = $serializer->serialize($book, 'json', ['groups' => 'getBooks']);
        $location = $urlGenerator->generate('detailBook', ['id' => $book->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse($jsonBook, Response::HTTP_CREATED, ['location' => $location], true);
    }

    #[Route('/api/books/{id}', name: 'updateBook', methods: ['PUT'])]

    public function updateBook(int $id, Request $request, SerializerInterface $serializer, BookRepository $bookRepository, EntityManagerInterface $em, AuthorRepository $authorRepository): JsonResponse
    {
        $currentBook = $bookRepository->find($id);
        $updateBook = $serializer->deserialize(
            $request->getContent(),
            Book::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $currentBook]
        );
        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;
        $updateBook->setAuthor($authorRepository->find($idAuthor));
        $em->persist($updateBook);
        $em->flush();
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}