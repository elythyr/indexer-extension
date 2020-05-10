<?php

namespace Phpactor\Indexer\Model;

use Phpactor\Indexer\Model\Query\ClassQuery;
use Phpactor\Indexer\Model\Record\FileRecord;
use Phpactor\Indexer\Model\Record\FunctionRecord;
use Phpactor\Indexer\Model\Record\MemberRecord;
use Phpactor\Name\FullyQualifiedName;

class IndexQueryAgent
{
    /**
     * @var Index
     */
    private $index;

    /**
     * @var ClassQuery
     */
    private $classQuery;


    public function __construct(Index $index)
    {
        $this->index = $index;
        $this->classQuery = new ClassQuery($index);
    }

    public function class(): ClassQuery
    {
        return $this->classQuery;
    }

    public function function(FullyQualifiedName $name): ?FunctionRecord
    {
        return $this->index->get(FunctionRecord::fromName($name));
    }

    public function file(string $path): ?FileRecord
    {
        return $this->index->get(FileRecord::fromPath($path));
    }

    public function member(string $name): ?MemberRecord
    {
        if (!MemberRecord::isIdentifier($name)) {
            return null;
        }

        return $this->index->get(MemberRecord::fromIdentifier($name));
    }
}
