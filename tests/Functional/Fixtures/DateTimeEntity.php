<?php
declare(strict_types=1);

namespace Paysera\Pagination\Tests\Functional\Fixtures;

use DateTime;

class DateTimeEntity
{
    /**
     * @var int|null
     */
    private $id;

    /**
     * @var DateTime|null
     */
    private $createdAt;

    /**
     * @return int|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return DateTime|null
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param DateTime $createdAt
     * @return $this
     */
    public function setCreatedAt(DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
