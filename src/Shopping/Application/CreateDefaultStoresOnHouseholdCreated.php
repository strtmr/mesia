<?php

declare(strict_types=1);

namespace App\Shopping\Application;

use App\Shared\Domain\Event\HouseholdCreated;
use App\Shopping\Domain\Model\Store;
use App\Shopping\Infrastructure\Doctrine\StoreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Uid\Uuid;

/**
 * Startliste der Läden. Ergänzt nur, was fehlt (wie der Katalog-Abgleich): Läden der Startliste, die der Haushalt
 * gelöscht hat, kommen nicht wieder, und selbst angelegte Läden gleichen Namens bleiben unberührt.
 * Läuft beim Anlegen eines Haushalts und bei jedem Deployment (app:household:initialize).
 */
#[AsEventListener]
final readonly class CreateDefaultStoresOnHouseholdCreated
{
    /**
     * seed_key => Name. Name ohne „@“, Schreibweise wie im Alltag. Bei Änderungen: seed_key nie wiederverwenden.
     */
    public const array DEFAULT_STORES = [
        // Discounter und Supermärkte
        'aldi' => 'Aldi',
        'lidl' => 'Lidl',
        'netto' => 'Netto',
        'penny' => 'Penny',
        'norma' => 'Norma',
        'edeka' => 'Edeka',
        'e-center' => 'E-Center',
        'marktkauf' => 'Marktkauf',
        'rewe' => 'Rewe',
        'kaufland' => 'Kaufland',
        'globus' => 'Globus',
        'hit' => 'HIT',
        'famila' => 'Famila',
        'combi' => 'Combi',
        'tegut' => 'tegut',
        'wasgau' => 'Wasgau',
        'nahkauf' => 'Nahkauf',
        'nah-und-frisch' => 'nah & frisch',
        'metro' => 'Metro',
        'selgros' => 'Selgros',
        // Bio und Reform
        'alnatura' => 'Alnatura',
        'denns' => 'Denns BioMarkt',
        'bio-company' => 'Bio Company',
        'reformhaus' => 'Reformhaus',
        // Drogerie
        'dm' => 'dm',
        'rossmann' => 'Rossmann',
        'mueller' => 'Müller',
        'budni' => 'Budni',
        // Getränke
        'getraenke-hoffmann' => 'Getränke Hoffmann',
        'trinkgut' => 'Trinkgut',
        'fristo' => 'Fristo',
        'hol-ab' => "Hol'ab!",
        'getraenkemarkt' => 'Getränkemarkt',
        // Lieferdienste
        'picnic' => 'Picnic',
        'flink' => 'Flink',
        'knuspr' => 'Knuspr',
        // Fachgeschäfte und Sonstiges
        'wochenmarkt' => 'Wochenmarkt',
        'baeckerei' => 'Bäckerei',
        'metzgerei' => 'Metzgerei',
        'hofladen' => 'Hofladen',
        'asia-markt' => 'Asia-Markt',
        'tuerkischer-supermarkt' => 'Türkischer Supermarkt',
        'apotheke' => 'Apotheke',
        'action' => 'Action',
        'baumarkt' => 'Baumarkt',
    ];

    public function __construct(
        private StoreRepository $stores,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(HouseholdCreated $event): void
    {
        $knownSeedKeys = $this->stores->seedKeys($event->householdId);
        $activeNames = array_map(static fn (Store $store): string => mb_strtolower($store->getName()), $this->stores->findAllActive($event->householdId));

        $now = $this->clock->now();
        $added = false;
        foreach (self::DEFAULT_STORES as $seedKey => $name) {
            if (\in_array($seedKey, $knownSeedKeys, true) || \in_array(mb_strtolower($name), $activeNames, true)) {
                continue;
            }
            $this->entityManager->persist(new Store(Uuid::v7(), $event->householdId, $name, $now, $seedKey));
            $added = true;
        }
        if ($added) {
            $this->entityManager->flush();
        }
    }
}
