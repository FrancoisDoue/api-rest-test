<?php

namespace App\Controller;

use App\Entity\Author;
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
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class AuthorController extends AbstractController
{
    #[Route('api/authors', name: 'app_author', methods: ['GET'])]

    public function getAllAuthors(AuthorRepository $authorRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 5);
        $idCache = 'getAllAuthors' . $page . '-' . $limit;
        // $authorList = $authorRepository->findAll();
        // note: Désactiver lazy loading est moins efficace que le gérer. Voir BookController et BookRepository pour la meilleure manière de faire
        $jsonAuthorList = $cache->get($idCache, function (ItemInterface $item) use ($authorRepository, $page, $limit, $serializer) {
            $item->tag('authorsCache');
            $item->expiresAfter(30*60);
            $authorList = $authorRepository->findAllWithPagination($page, $limit);
            return $serializer->serialize($authorList, 'json', ['groups' => 'getAuthors']);
        });
        return new JsonResponse($jsonAuthorList, Response::HTTP_OK, [], true);
    }

    #[Route('api/authors/{id}', name: 'detailAuthor', methods: ['GET'])]

    public function getDetailAuthor(int $id, SerializerInterface $serializer, AuthorRepository $authorRepository): JsonResponse
    {

        $author = $authorRepository->find($id);
        if ($author) {
            $jsonAuthor = $serializer->serialize($author, 'json', ['groups' => 'getAuthors']);
            return new JsonResponse($jsonAuthor, Response::HTTP_OK, [], true);
        }
        return new JsonResponse(['message' => 'Ressource introuvable', 'error' => Response::HTTP_NOT_FOUND], Response::HTTP_NOT_FOUND);
    }

    #[Route('api/authors/{id}', name: 'deleteAuthor', methods: ['DELETE'])]

    public function deleteAuthor(int $id, AuthorRepository $authorRepository, EntityManagerInterface $em, TagAwareCacheInterface $cache): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN', null, "Vous n'avez pas les autorisations nécessaires pour supprimer un Auteur");
        $cache->invalidateTags(['authorsCache']);
        $author = $authorRepository->find($id);
        $em->remove($author);
        $em->flush();
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[route('api/authors', name: 'createAuthor', methods: ['POST'])]

    public function createAuthor(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, BookRepository $bookRepository, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN', null, "Vous n'avez pas les autorisations nécessaires pour ajouter un Auteur");
        $cache->invalidateTags(['authorsCache']);
        $author = $serializer->deserialize($request->getContent(), Author::class, 'json');
        $content = $request->toArray();
        $idBooks = $content['idBook'] ?? -1;
        if (is_array($idBooks)) {
            foreach ($idBooks as $id) $author->addBook($bookRepository->find($id));
        } else {
            $author->addBook($bookRepository->find($idBooks));
        }
        $errors = $validator->validate($author);
        if($errors->count() > 0){
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST,[],true);
        }
        $em->persist($author);
        $em->flush();
        $jsonAuthor = $serializer->serialize($author, 'json', ['groups' => 'getAuthor']);
        $location = $urlGenerator->generate('detailAuthor', ['id' => $author->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse(
            $jsonAuthor,
            Response::HTTP_CREATED,
            ['location' => $location],
            true
        );
    }

    #[Route('api/authors/{id}', name: 'updateAuthor', methods: ['PUT'])]

    public function updateAuthor(int $id, Request $request, SerializerInterface $serializer, AuthorRepository $authorRepository, EntityManagerInterface $em, BookRepository $bookRepository, TagAwareCacheInterface $cache): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN', null, "Vous n'avez pas les autorisations nécessaires pour modifier un Auteur");
        $cache->invalidateTags(['authorsCache']);
        $currentAuthor = $authorRepository->find($id);
        $updateAuthor = $serializer->deserialize(
            $request->getContent(),
            Author::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $currentAuthor]
        );
        $content = $request->toArray();
        $idBooks = $content['idBook'] ?? -1;
        if (is_array($idBooks)) {
            foreach ($idBooks as $id) $updateAuthor->addBook($bookRepository->find($id));
        } else {
            $updateAuthor->addBook($bookRepository->find($idBooks));
        }
        $em->persist($updateAuthor);
        $em->flush();
        return new JsonResponse();
    }
}