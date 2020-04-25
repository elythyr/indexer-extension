<?php

namespace Phpactor\Indexer\Adapter\Tolerant;

use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\QualifiedName;
use Microsoft\PhpParser\Node\Statement\ClassDeclaration;
use Microsoft\PhpParser\Node\Statement\FunctionDeclaration;
use Microsoft\PhpParser\Parser;
use Phpactor\Indexer\Model\Index;
use Phpactor\Indexer\Model\IndexBuilder;
use Phpactor\Indexer\Model\Record\ClassRecord;
use Phpactor\Indexer\Model\Record\FunctionRecord;
use Phpactor\Name\FullyQualifiedName;
use Phpactor\TextDocument\ByteOffset;
use SplFileInfo;

class TolerantIndexBuilder implements IndexBuilder
{
    /**
     * @var Index
     */
    private $index;

    /**
     * @var Parser
     */
    private $parser;

    public function __construct(
        Index $index,
        ?Parser $parser = null
    ) {
        $this->index = $index;
        $this->parser = $parser ?: new Parser();
    }

    public function index(SplFileInfo $info): void
    {
        $contents = @file_get_contents($info->getPathname());

        if (false === $contents) {
            return;
        }

        $node = $this->parser->parseSourceFile($contents, $info->getPathname());
        $this->indexNode($info, $node);
    }

    public function done(): void
    {
        $this->index->updateTimestamp();
    }

    private function indexNode(SplFileInfo $info, Node $node): void
    {
        if ($node instanceof ClassDeclaration) {
            $this->indexClassDeclaration($info, $node);
            return;
        }

        if ($node instanceof FunctionDeclaration) {
            $this->indexFunction($info, $node);
            return;
        }

        foreach ($node->getChildNodes() as $childNode) {
            $this->indexNode($info, $childNode);
        }
    }

    private function indexClassDeclaration(SplFileInfo $info, ClassDeclaration $node): void
    {
        $record = $this->index->get(ClassRecord::fromName($node->getNamespacedName()->getFullyQualifiedNameText()));
        assert($record instanceof ClassRecord);
        $record->setLastModified($info->getCTime());
        $record->setStart(ByteOffset::fromInt($node->getStart()));
        $record->setType('class');
        $record->setFilePath($info->getPathname());

        // remove any references to this class and other classes before
        // updating with the current data
        $this->removeImplementations($record);
        $record->clearImplemented();

        $this->indexClassInterfaces($record, $node);
        $this->indexBaseClass($record, $node);

        $this->index->write($record);
    }

    private function indexClassInterfaces(ClassRecord $classRecord, ClassDeclaration $node): void
    {
        // @phpstan-ignore-next-line because ClassInterfaceClause _can_ (and has been) be NULL
        if (null === $interfaceClause = $node->classInterfaceClause) {
            return;
        }

        if (null == $interfaceList = $interfaceClause->interfaceNameList) {
            return;
        }

        foreach ($interfaceList->children as $interfaceName) {
            if (!$interfaceName instanceof QualifiedName) {
                continue;
            }

            $classRecord->addImplements(FullyQualifiedName::fromString($interfaceName->getNamespacedName()->getFullyQualifiedNameText()));

            $interfaceRecord = $this->index->get(ClassRecord::fromName($interfaceName));
            assert($interfaceRecord instanceof ClassRecord);
            $interfaceRecord->addImplementation($classRecord->fqn());

            $this->index->write($interfaceRecord);
        }
    }

    private function indexBaseClass(ClassRecord $record, ClassDeclaration $node): void
    {
        // @phpstan-ignore-next-line because classBaseClause _can_ be NULL
        if (null === $baseClause = $node->classBaseClause) {
            return;
        }

        // @phpstan-ignore-next-line because classBaseClause _can_ be NULL
        if (null === $baseClass = $baseClause->baseClass) {
            return;
        }

        $name = $baseClass->getNamespacedName()->getFullyQualifiedNameText();
        $record->addImplements(FullyQualifiedName::fromString($name));
        $baseClassRecord = $this->index->get(ClassRecord::fromName($name));
        assert($baseClassRecord instanceof ClassRecord);
        $baseClassRecord->addImplementation($record->fqn());
        $this->index->write($baseClassRecord);
    }

    private function indexFunction(SplFileInfo $info, FunctionDeclaration $node): void
    {
        $record = $this->index->get(FunctionRecord::fromName($node->getNamespacedName()->getFullyQualifiedNameText()));
        assert($record instanceof FunctionRecord);
        $record->setLastModified($info->getCTime());
        $record->setStart(ByteOffset::fromInt($node->getStart()));
        $record->setFilePath($info->getPathname());
        $this->index->write($record);
    }

    private function removeImplementations(ClassRecord $record): void
    {
        foreach ($record->implements() as $implementedClass) {
            $implementedRecord = $this->index->get(ClassRecord::fromName($implementedClass));
        
            if (false === $implementedRecord->removeImplementation($record->fqn())) {
                continue;
            }
        
            $this->index->write($implementedRecord);
        }
    }
}
