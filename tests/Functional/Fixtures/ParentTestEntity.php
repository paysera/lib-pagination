<?php
declare(strict_types=1);

namespace Paysera\Pagination\Tests\Functional\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class ParentTestEntity
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
     * @var string|null
     */
    private $groupKey;

    /**
     * @var ChildTestEntity[]|Collection
     */
    private $children;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

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
     * @return Collection|ChildTestEntity[]
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(ChildTestEntity $child): self
    {
        $this->children[] = $child;
        $child->setParent($this);
        return $this;
    }

    /**
     * @param Collection|ChildTestEntity[] $children
     * @return $this
     */
    public function setChildren($children)
    {
        $this->children->clear();
        foreach ($children as $child) {
            $this->addChild($child);
        }

        return $this;
    }

    /**
     * @return string|null
     */
    public function getGroupKey()
    {
        return $this->groupKey;
    }

    /**
     * @param string|null $groupKey
     *
     * @return $this
     */
    public function setGroupKey(string $groupKey): self
    {
        $this->groupKey = $groupKey;
        return $this;
    }
}
