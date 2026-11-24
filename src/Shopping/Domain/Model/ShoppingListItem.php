<?php

declare(strict_types=1);

namespace App\Shopping\Domain\Model;

use App\Catalog\Domain\Unit;
use App\Shared\Domain\Syncable;
use App\Shared\Domain\SyncableFields;
use App\Shopping\Domain\ContributionSource;
use App\Shopping\Infrastructure\Doctrine\ShoppingListItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Ein Eintrag auf der Einkaufsliste (Aggregat mit seinen Beiträgen).
 *
 * Regeln (docs/spezifikation-phase-0-1.md, Kapitel 3.3 und 4.4):
 * - Abhaken löscht nicht. Der Eintrag bleibt als "Erledigt" stehen.
 * - Kommt ein erledigter Artikel erneut auf die Liste, wird derselbe Eintrag reaktiviert. Alte Beiträge werden
 *   archiviert, und eine manuell gesetzte Menge wird zurückgesetzt.
 * - Ein Abhaken, das älter ist als das letzte Reaktivieren, wird ignoriert (Zeitpunkt vom Gerät).
 * - Jede Änderung an Beiträgen aktualisiert den Eintrag selbst. So landet der Gesamtzustand im Änderungsprotokoll.
 * - Läden („Bier nur bei Edeka“) bleiben beim Reaktivieren erhalten, „gültig bis“ (z. B. Angebot) nicht.
 */
#[ORM\Entity(repositoryClass: ShoppingListItemRepository::class)]
#[ORM\Table(name: 'shopping_list_item')]
#[ORM\Index(name: 'shopping_list_item_household_idx', columns: ['household_id'])]
#[ORM\UniqueConstraint(name: 'shopping_list_item_article_unique', columns: ['list_id', 'article_id'], options: ['where' => '((deleted_at IS NULL) AND (article_id IS NOT NULL))'])]
class ShoppingListItem implements Syncable
{
    use SyncableFields;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $listId;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?Uuid $articleId;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $categoryId;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $note;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3, nullable: true)]
    private ?string $qtyOverride = null;

    #[ORM\Column(length: 16, nullable: true, enumType: Unit::class)]
    private ?Unit $unitOverride = null;

    #[ORM\Column(length: 64, options: ['collation' => 'C'])]
    private string $sortKey;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $checkedAt = null;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?Uuid $checkedBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $activatedAt;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?Uuid $createdBy;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?Uuid $updatedBy;

    /**
     * Läden, in denen der Artikel gekauft werden soll (leer = egal wo).
     *
     * @var list<string> UUIDs
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $storeIds = [];

    /** Letzter Tag, an dem sich der Kauf lohnt (z. B. Ende eines Angebots) */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validUntil = null;

    /** @var Collection<int, ItemContribution> */
    #[ORM\OneToMany(targetEntity: ItemContribution::class, mappedBy: 'item', cascade: ['persist'])]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $contributions;

    public function __construct(
        Uuid $id,
        Uuid $householdId,
        Uuid $listId,
        ?Uuid $articleId,
        string $name,
        Uuid $categoryId,
        string $sortKey,
        ?string $note,
        Uuid $actorId,
        \DateTimeImmutable $at,
        \DateTimeImmutable $now,
    ) {
        $this->id = $id;
        $this->initSyncableFields($householdId, $now);
        $this->listId = $listId;
        $this->articleId = $articleId;
        $this->setName($name);
        $this->categoryId = $categoryId;
        $this->sortKey = $sortKey;
        $this->setNote($note);
        $this->activatedAt = $at;
        $this->createdBy = $actorId;
        $this->updatedBy = $actorId;
        $this->contributions = new ArrayCollection();
    }

    public static function syncEntityType(): string
    {
        return 'shopping_item';
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getListId(): Uuid
    {
        return $this->listId;
    }

    public function getArticleId(): ?Uuid
    {
        return $this->articleId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCategoryId(): Uuid
    {
        return $this->categoryId;
    }

    public function isChecked(): bool
    {
        return null !== $this->checkedAt;
    }

    public function getCheckedAt(): ?\DateTimeImmutable
    {
        return $this->checkedAt;
    }

    /**
     * @return list<ItemContribution>
     */
    public function activeContributions(): array
    {
        return array_values($this->contributions->filter(static fn (ItemContribution $c): bool => $c->isActive())->toArray());
    }

    /**
     * Fügt einen Beitrag hinzu. Ist der Eintrag erledigt, wird er dabei reaktiviert.
     */
    public function addContribution(Uuid $contributionId, ContributionSource $source, ?Uuid $sourceRef, ?string $quantity, ?Unit $unit, Uuid $actorId, \DateTimeImmutable $at, \DateTimeImmutable $now): void
    {
        if ($this->isChecked()) {
            $this->reactivate($contributionId, $source, $sourceRef, $quantity, $unit, $actorId, $at, $now);

            return;
        }

        $this->contributions->add(new ItemContribution($contributionId, $this, $source, $sourceRef, $quantity, $unit, $actorId, $now));
        $this->changedBy($actorId, $now);
    }

    /**
     * "Wieder auf die Liste": alte Beiträge archivieren, manuelle Menge zurücksetzen, neuen Beitrag anlegen.
     */
    public function reactivate(Uuid $contributionId, ContributionSource $source, ?Uuid $sourceRef, ?string $quantity, ?Unit $unit, Uuid $actorId, \DateTimeImmutable $at, \DateTimeImmutable $now): void
    {
        foreach ($this->activeContributions() as $contribution) {
            $contribution->deactivate();
        }

        $this->checkedAt = null;
        $this->checkedBy = null;
        $this->validUntil = null;
        $this->activatedAt = $at > $this->activatedAt ? $at : $this->activatedAt;
        $this->qtyOverride = null;
        $this->unitOverride = null;
        $this->contributions->add(new ItemContribution($contributionId, $this, $source, $sourceRef, $quantity, $unit, $actorId, $now));
        $this->changedBy($actorId, $now);
    }

    /**
     * @return bool false, wenn das Abhaken veraltet ist oder der Eintrag schon erledigt war
     */
    public function check(Uuid $actorId, \DateTimeImmutable $at, \DateTimeImmutable $now): bool
    {
        if ($this->isChecked() || $at < $this->activatedAt) {
            return false;
        }

        $this->checkedAt = $at;
        $this->checkedBy = $actorId;
        $this->changedBy($actorId, $now);

        return true;
    }

    /**
     * Rückgängig machen des Abhakens. Die Beiträge bleiben erhalten.
     *
     * @return bool false, wenn der Eintrag nicht erledigt war
     */
    public function uncheck(Uuid $actorId, \DateTimeImmutable $now): bool
    {
        if (!$this->isChecked()) {
            return false;
        }

        $this->checkedAt = null;
        $this->checkedBy = null;
        $this->changedBy($actorId, $now);

        return true;
    }

    public function rename(string $name, Uuid $actorId, \DateTimeImmutable $now): void
    {
        $this->setName($name);
        $this->changedBy($actorId, $now);
    }

    public function changeNote(?string $note, Uuid $actorId, \DateTimeImmutable $now): void
    {
        $this->setNote($note);
        $this->changedBy($actorId, $now);
    }

    /**
     * Manuell gesetzte Gesamtmenge. Danach überschreibt keine Quelle mehr die angezeigte Menge.
     */
    public function overrideQuantity(?string $quantity, ?Unit $unit, Uuid $actorId, \DateTimeImmutable $now): void
    {
        $this->qtyOverride = $quantity;
        $this->unitOverride = null === $quantity ? null : $unit;
        $this->changedBy($actorId, $now);
    }

    /**
     * @return bool true, wenn sich die Kategorie geändert hat
     */
    public function moveTo(Uuid $categoryId, string $sortKey, Uuid $actorId, \DateTimeImmutable $now): bool
    {
        $categoryChanged = !$this->categoryId->equals($categoryId);
        $this->categoryId = $categoryId;
        $this->sortKey = $sortKey;
        $this->changedBy($actorId, $now);

        return $categoryChanged;
    }

    /**
     * Kategorie wurde gelöscht: Eintrag in die Fallback-Kategorie verschieben (Position bleibt).
     */
    public function reassignCategory(Uuid $categoryId, \DateTimeImmutable $now): void
    {
        if (!$this->categoryId->equals($categoryId)) {
            $this->categoryId = $categoryId;
            $this->touch($now);
        }
    }

    /**
     * @param list<Uuid> $storeIds ersetzt die bisherigen Läden
     */
    public function assignStores(array $storeIds, Uuid $actorId, \DateTimeImmutable $now): void
    {
        $ids = array_values(array_unique(array_map(static fn (Uuid $id): string => $id->toRfc4122(), $storeIds)));
        if ($ids !== $this->storeIds) {
            $this->storeIds = $ids;
            $this->changedBy($actorId, $now);
        }
    }

    /**
     * Läden ergänzen (Schnelleingabe „Bier @Edeka“ für einen Artikel, der schon auf der Liste steht).
     *
     * @param list<Uuid> $storeIds
     */
    public function addStores(array $storeIds, Uuid $actorId, \DateTimeImmutable $now): void
    {
        $this->assignStores([...array_map(Uuid::fromString(...), $this->storeIds), ...$storeIds], $actorId, $now);
    }

    public function setValidUntil(?\DateTimeImmutable $date, Uuid $actorId, \DateTimeImmutable $now): void
    {
        $date = $date?->setTime(0, 0);
        if ($date?->format('Y-m-d') !== $this->validUntil?->format('Y-m-d')) {
            $this->validUntil = $date;
            $this->changedBy($actorId, $now);
        }
    }

    /**
     * @return list<string>
     */
    public function getStoreIds(): array
    {
        return $this->storeIds;
    }

    public function getValidUntil(): ?\DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function delete(Uuid $actorId, \DateTimeImmutable $now): void
    {
        $this->updatedBy = $actorId;
        $this->softDelete($now);
    }

    /**
     * Gesamtmenge für Anzeige und Kaufhistorie: manuelle Menge, sonst Summe der aktiven Beiträge mit gleicher Einheit.
     * null, wenn keine Menge bekannt ist oder sich die Einheiten nicht addieren lassen.
     *
     * @return array{quantity: string, unit: ?Unit}|null
     */
    public function effectiveQuantity(): ?array
    {
        if (null !== $this->qtyOverride) {
            return ['quantity' => $this->qtyOverride, 'unit' => $this->unitOverride];
        }

        $withQuantity = array_filter($this->activeContributions(), static fn (ItemContribution $c): bool => null !== $c->getQuantity());
        if ([] === $withQuantity) {
            return null;
        }

        $units = array_unique(array_map(static fn (ItemContribution $c): string => $c->getUnit()->value ?? '', $withQuantity));
        if (1 !== \count($units)) {
            return null;
        }

        $sum = array_sum(array_map(static fn (ItemContribution $c): float => (float) $c->getQuantity(), $withQuantity));

        return ['quantity' => rtrim(rtrim(number_format($sum, 3, '.', ''), '0'), '.'), 'unit' => reset($withQuantity)->getUnit()];
    }

    public function toSyncSnapshot(): array
    {
        return [
            'id' => $this->id->toRfc4122(),
            'list_id' => $this->listId->toRfc4122(),
            'article_id' => $this->articleId?->toRfc4122(),
            'name' => $this->name,
            'category_id' => $this->categoryId->toRfc4122(),
            'note' => $this->note,
            'qty_override' => null === $this->qtyOverride ? null : (float) $this->qtyOverride,
            'unit_override' => $this->unitOverride?->value,
            'sort_key' => $this->sortKey,
            'checked_at' => $this->checkedAt?->format(\DATE_ATOM),
            'checked_by' => $this->checkedBy?->toRfc4122(),
            'activated_at' => $this->activatedAt->format(\DATE_ATOM),
            'store_ids' => $this->storeIds,
            'valid_until' => $this->validUntil?->format('Y-m-d'),
            'contributions' => array_map(static fn (ItemContribution $c): array => $c->toSnapshot(), $this->activeContributions()),
        ] + $this->syncMeta();
    }

    private function setName(string $name): void
    {
        $name = trim($name);
        if ('' === $name || mb_strlen($name) > 100) {
            throw new \InvalidArgumentException('Der Name muss 1 bis 100 Zeichen lang sein.');
        }
        $this->name = $name;
    }

    private function setNote(?string $note): void
    {
        $note = null === $note ? null : trim($note);
        if (null !== $note && mb_strlen($note) > 200) {
            throw new \InvalidArgumentException('Die Notiz darf höchstens 200 Zeichen lang sein.');
        }
        $this->note = '' === $note ? null : $note;
    }

    private function changedBy(Uuid $actorId, \DateTimeImmutable $now): void
    {
        $this->updatedBy = $actorId;
        $this->touch($now);
    }
}
