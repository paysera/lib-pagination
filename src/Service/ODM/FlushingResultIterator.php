<?php
declare(strict_types=1);

namespace Paysera\Pagination\Service\ODM;

use Doctrine\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;

class FlushingResultIterator extends ResultIterator
{
    private $objectManager;

    public function __construct(
        ResultProvider $resultProvider,
        LoggerInterface $logger,
        int $defaultPageSize,
        ObjectManager $objectManager
    ) {
        parent::__construct($resultProvider, $logger, $defaultPageSize);
        $this->objectManager = $objectManager;
    }

    protected function handleCycleEnd()
    {
        $this->objectManager->flush();
        $this->objectManager->clear();
    }
}
