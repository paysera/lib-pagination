<?php
declare(strict_types=1);

namespace Paysera\Pagination\Service\Doctrine;

use Paysera\Pagination\Entity\Doctrine\ConfiguredQuery;
use Paysera\Pagination\Entity\Pager;
use Psr\Log\LoggerInterface;
use Generator;

class ResultIterator
{
    private $resultProvider;
    private $logger;
    private $defaultPageSize;

    public function __construct(
        ResultProvider $resultProvider,
        LoggerInterface $logger,
        int $defaultPageSize
    ) {
        $this->resultProvider = $resultProvider;
        $this->logger = $logger;
        $this->defaultPageSize = $defaultPageSize;
    }

    public function iterate(ConfiguredQuery $configuredQuery, Pager $startPager = null): Generator
    {
        $pager = $startPager !== null ? clone $startPager : new Pager();
        if ($pager->getLimit() === null) {
            $pager->setLimit($this->defaultPageSize);
        }

        while (true) {
            $result = $this->resultProvider->getResultForQuery($configuredQuery, $pager);

            foreach ($result->getItems() as $item) {
                yield $item;
            }

            $this->handleCycleEnd();

            if (!$result->hasNext()) {
                $this->logger->info('Finished iterating');
                return;
            }

            $pager = (new Pager())
                ->setOrderingPairs($pager->getOrderingPairs())
                ->setLimit($pager->getLimit())
                ->setAfter($result->getNextCursor())
            ;

            $this->logger->info('Continuing with iteration', ['after' => $result->getNextCursor()]);
        }
    }

    protected function handleCycleEnd()
    {
        // intentionally empty – override in extended classes
    }
}
