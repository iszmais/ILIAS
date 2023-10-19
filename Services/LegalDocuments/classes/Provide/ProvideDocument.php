<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

namespace ILIAS\LegalDocuments\Provide;

use Closure;
use ILIAS\DI\Container;
use ILIAS\Data\Result;
use ILIAS\Data\Result\Error;
use ILIAS\Data\Result\Ok;
use ILIAS\LegalDocuments\ChangeSet;
use ILIAS\LegalDocuments\Change\AcceptedDocument;
use ILIAS\LegalDocuments\Change\AddDocument;
use ILIAS\LegalDocuments\Condition;
use ILIAS\LegalDocuments\Legacy\Table;
use ILIAS\LegalDocuments\Repository\DocumentRepository;
use ILIAS\LegalDocuments\Table\DocumentTable;
use ILIAS\LegalDocuments\Table\EditableDocumentTable;
use ILIAS\LegalDocuments\Value\Document;
use ILIAS\LegalDocuments\Value\DocumentContent;
use ILIAS\LegalDocuments\Value\Criterion;
use ILIAS\LegalDocuments\Value\CriterionContent;
use ILIAS\LegalDocuments\Value\Document as DocumentValue;
use ILIAS\LegalDocuments\TableConfig;
use ILIAS\UI\Component\Component;
use ILIAS\LegalDocuments\EditLinks;
use InvalidArgumentException;
use ILIAS\LegalDocuments\Table\DocumentModal;
use ILIAS\LegalDocuments\ConditionDefinition;
use ILIAS\LegalDocuments\ConsumerToolbox\UI;
use ILIAS\LegalDocuments\Table as TableInterface;
use ilObjUser;

class ProvideDocument
{
    public const CRITERION_ALREADY_EXISTS = 'Criterion already exists.';
    public const CRITERION_WOULD_NEVER_MATCH = 'Criterion would never match.';

    /** @var Closure(object, string, TableInterface): Table */
    private readonly Closure $create_table_gui;

    /**
     * @param null|Closure(object, string, TableInterface): Table $create_table_gui
     * @param array<string, ConditionDefinition> $conditions
     * @param array<string, Closure(CriterionContent): Component $content_as_component
     */
    public function __construct(
        private readonly string $id,
        private readonly DocumentRepository $document_repository,
        private readonly array $conditions,
        private readonly array $content_as_component,
        private readonly Container $container,
        ?Closure $create_table_gui = null
    ) {
        $this->create_table_gui = $create_table_gui ?? fn($gui, $command, $t) => new Table($gui, $command, $t);
    }

    public function table(object $gui, string $command, ?EditLinks $edit_links = null): Component
    {
        $t = new DocumentTable(
            fn($criterion) => $this->toCondition($criterion)->asComponent(),
            $this->document_repository,
            new UI($this->id, $this->container->ui()->factory(), $this->container->ui()->mainTemplate(), $this->container->language()),
            new DocumentModal($this->container->ui(), $this->contentAsComponent(...))
        );

        if ($edit_links !== null) {
            $t = new EditableDocumentTable($t, $edit_links);
        }

        $table = ($this->create_table_gui)($gui, $command, $t);

        return $this->container->ui()->factory()->legacy($table->getHTML());
    }

    public function chooseDocumentFor(ilObjUser $user): Result
    {
        $document_matches = fn(Document $document): bool => $this->all(
            fn($c) => $this->toCondition($c->content())->eval($user),
            $document->criteria()
        );

        return $this->find($document_matches, $this->document_repository->all());
    }

    public function repository(): DocumentRepository
    {
        return $this->document_repository;
    }

    public function hash(): string
    {
        return bin2hex(openssl_random_pseudo_bytes((int) floor(255 / 2)));
    }

    public function toCondition(CriterionContent $content): Condition
    {
        return $this->conditions[$content->type()]->withCriterion($content);
    }

    public function conditionGroups(?CriterionContent $criterion = null): array
    {
        $selected = $criterion ? [
            $criterion->type() => $criterion->arguments()
        ] : [];

        return array_combine(array_keys($this->conditions), array_map(
            fn(ConditionDefinition $condition, string $key) => $condition->formGroup($selected[$key] ?? []),
            array_values($this->conditions),
            array_keys($this->conditions)
        ));
    }

    /**
     * @param list<Criterion> $criteria
     */
    public function validateCriteriaContent(array $criteria, CriterionContent $content): Result
    {
        return array_reduce(
            $criteria,
            $this->validateAgainst(...),
            new Ok($content)
        );
    }

    public function contentAsComponent(DocumentContent $content): Component
    {
        return $this->content_as_component[$content->type()]($content);
    }

    private function validateAgainst(Result $b, Criterion $a): Result
    {
        return $b->then(function (CriterionContent $b) use ($a) {
            $a = $a->content();
            if ($a->equals($b)) {
                return new Error(self::CRITERION_ALREADY_EXISTS);
            }
            $a = $this->toCondition($a);
            if ($a->knownToNeverMatchWith($this->toCondition($b))) {
                return new Error(self::CRITERION_WOULD_NEVER_MATCH);
            }
            return new Ok($b);
        });
    }

    /**
     * @template A
     * @param Closure(A): bool $predicate
     * @param list<A> $array
     * @return Result<A>
     */
    private function find(Closure $predicate, array $array): Result
    {
        foreach ($array as $x) {
            if ($predicate($x)) {
                return new Ok($x);
            }
        }

        return new Error('Not found.');
    }

    /**
     * @template A
     * @param Closure(A): bool $predicate
     * @param list<A> $array
     */
    private function any(Closure $predicate, array $array): bool
    {
        return $this->find($predicate, $array)->isOk();
    }

    /**
     * @template A
     * @param Closure(A): bool $predicate
     * @param list<A> $array
     */
    private function all(Closure $predicate, array $array): bool
    {
        return !$this->any(static fn($x) => !$predicate($x), $array);
    }
}
