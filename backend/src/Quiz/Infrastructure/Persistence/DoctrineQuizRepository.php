<?php

namespace App\Quiz\Infrastructure\Persistence;

use App\Identity\Domain\Model\User;
use App\Quiz\Domain\Model\Quiz;
use App\Quiz\Domain\Repository\QuizRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/** @extends ServiceEntityRepository<Quiz> */
#[AsAlias(QuizRepository::class)]
class DoctrineQuizRepository extends ServiceEntityRepository implements QuizRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quiz::class);
    }

    public function ofId(int $id): ?Quiz
    {
        return $this->find($id);
    }

    public function findOneByTitle(string $title): ?Quiz
    {
        return $this->findOneBy(['title' => $title]);
    }

    /** @return Quiz[] */
    public function findByOwner(User $owner): array
    {
        return $this->findBy(['owner' => $owner]);
    }

    /** @return Quiz[] */
    public function search(?string $term, ?string $category, ?User $owner): array
    {
        $qb = $this->createQueryBuilder('q')
            ->leftJoin('q.questions', 'question')->addSelect('question')
            ->orderBy('q.createdAt', 'DESC');

        if ($term) {
            $qb->andWhere('LOWER(q.title) LIKE :term OR LOWER(q.description) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower($term).'%');
        }

        if ($category) {
            $qb->andWhere('q.category = :category')->setParameter('category', $category);
        }

        if ($owner) {
            $qb->andWhere('q.owner = :owner')->setParameter('owner', $owner);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return string[] */
    public function findCategories(): array
    {
        $rows = $this->createQueryBuilder('q')
            ->select('DISTINCT q.category')
            ->orderBy('q.category', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($rows, 'category');
    }

    public function countAll(): int
    {
        return $this->count();
    }

    public function anonymizeOwner(User $owner, string $anonymous): void
    {
        $this->createQueryBuilder('q')
            ->update()
            ->set('q.owner', ':none')
            ->set('q.author', ':anonymous')
            ->andWhere('q.owner = :owner')
            ->setParameter('none', null)
            ->setParameter('anonymous', $anonymous)
            ->setParameter('owner', $owner)
            ->getQuery()
            ->execute();
    }

    public function add(Quiz $quiz): void
    {
        $this->getEntityManager()->persist($quiz);
    }

    public function remove(Quiz $quiz): void
    {
        $this->getEntityManager()->remove($quiz);
    }
}
