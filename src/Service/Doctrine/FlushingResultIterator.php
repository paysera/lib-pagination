<?php
declare(strict_types=1);

namespace Paysera\Pagination\Service\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class FlushingResultIterator extends ResultIterator
{
    private $entityManager;

    public function __construct(
        ResultProvider $resultProvider,
        LoggerInterface $logger,
        int $defaultPageSize,
        EntityManagerInterface $entityManager
    ) {
        parent::__construct($resultProvider, $logger, $defaultPageSize);
        $this->entityManager = $entityManager;
    }

    protected function handleCycleEnd()
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
