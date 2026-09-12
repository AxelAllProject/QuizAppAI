<?php

namespace App\Repository;

use App\Entity\Quiz;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Quiz> */
class QuizRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quiz::class);
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
}
