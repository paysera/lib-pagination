<?php
declare(strict_types=1);

namespace Paysera\Pagination\Tests\Functional\Fixtures;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\GeneratedValue;

/**
 * @Entity
 */
class ChildTestEntity
{
    /**
     * @var int|null
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     */
    private $id;

    /**
     * @var string|null
     *
     * @Column(type="string")
     */
    private $name;

    /**
     * @var ParentTestEntity
     *
     * @ManyToOne(targetEntity="ParentTestEntity", inversedBy="children")
     * @JoinColumn(name="parent_id")
     */
    private $parent;

    /**
     * @return int|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string|null
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return ParentTestEntity
     */
    public function getParent(): ParentTestEntity
    {
        return $this->parent;
    }

    /**
     * @param ParentTestEntity $parent
     * @return $this
     */
    public function setParent(ParentTestEntity $parent): self
    {
        $this->parent = $parent;
        return $this;
    }
}
