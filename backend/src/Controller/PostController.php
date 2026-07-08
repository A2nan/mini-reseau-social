<?php

namespace App\Controller;

use App\Entity\Post;
use App\Repository\PostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class PostController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PostRepository $posts,
    ) {
    }

    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    #[Route('/posts', name: 'posts_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $data = array_map(
            static fn (Post $p) => $p->toArray(),
            $this->posts->findBy([], ['createdAt' => 'DESC'])
        );

        return new JsonResponse($data);
    }

    #[Route('/posts/{id}', name: 'posts_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $post = $this->posts->find($id);
        if (!$post) {
            return new JsonResponse(['error' => 'Post not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($post->toArray());
    }

    #[Route('/posts', name: 'posts_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $author = trim((string) ($data['author'] ?? ''));
        $content = trim((string) ($data['content'] ?? ''));

        if ('' === $author || '' === $content) {
            return new JsonResponse(
                ['error' => 'Les champs "author" et "content" sont obligatoires.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $post = new Post();
        $post->setAuthor($author)->setContent($content);

        $this->em->persist($post);
        $this->em->flush();

        return new JsonResponse($post->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/posts/{id}', name: 'posts_update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $post = $this->posts->find($id);
        if (!$post) {
            return new JsonResponse(['error' => 'Post not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['author'])) {
            $post->setAuthor(trim((string) $data['author']));
        }
        if (isset($data['content'])) {
            $post->setContent(trim((string) $data['content']));
        }
        if (isset($data['likes'])) {
            $post->setLikes((int) $data['likes']);
        }

        $this->em->flush();

        return new JsonResponse($post->toArray());
    }

    #[Route('/posts/{id}', name: 'posts_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $post = $this->posts->find($id);
        if (!$post) {
            return new JsonResponse(['error' => 'Post not found'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($post);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}

// pipeline CI/CD demo
