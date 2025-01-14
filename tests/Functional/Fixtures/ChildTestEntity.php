<?php
declare(strict_types=1);

namespace Paysera\Pagination\Tests\Functional\Fixtures;

class ChildTestEntity
{
    /**
     * @var int|null
     */
    private $id;

    /**
     * @var string|null
     */
    private $name;

    /**
     * @var ParentTestEntity
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
