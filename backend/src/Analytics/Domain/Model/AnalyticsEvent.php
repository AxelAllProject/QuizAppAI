<?php

namespace App\Analytics\Domain\Model;

use App\Identity\Domain\Model\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un fait mesurable : une page consultée, un quiz lancé, une génération IA réussie ou non.
 *
 * Minimisation (RGPD) : ni adresse IP, ni user-agent, ni empreinte de navigateur — donc
 * aucun traceur, aucun cookie, et rien qui sorte du serveur. Le lien vers le compte sert
 * à compter les personnes actives ; il est coupé (SET NULL) dès que le compte est supprimé,
 * tandis que le rôle, recopié ici, garde les chiffres lisibles après coup.
 *
 * Le chemin est **normalisé** avant d'être écrit (« /quiz/42 » → « /quiz/:id ») : on mesure
 * des écrans, pas des contenus individuels, et un identifiant d'URL n'est pas une statistique.
 */
#[ORM\Entity]
#[ORM\Table(name: 'analytics_event')]
// Toutes les agrégations partent d'une fenêtre de temps, souvent restreinte à un nom d'événement.
#[ORM\Index(fields: ['occurredAt'])]
#[ORM\Index(fields: ['name', 'occurredAt'])]
class AnalyticsEvent
{
    /** Page consultée : envoyé par le frontend à chaque changement d'adresse. */
    public const PAGE_VIEW = 'page_view';

    /** Quiz solo ouvert (frontend) puis terminé (serveur) : leur écart donne l'abandon. */
    public const QUIZ_STARTED = 'quiz_started';
    public const QUIZ_FINISHED = 'quiz_finished';

    /** Actions clés enregistrées côté serveur : elles ne peuvent pas être falsifiées par le navigateur. */
    public const SIGNUP = 'signup';
    public const LOGIN = 'login';
    public const QUIZ_CREATED = 'quiz_created';
    public const LIVE_HOSTED = 'live_hosted';
    public const LIVE_JOINED = 'live_joined';
    public const AI_GENERATION = 'ai_generation';
    public const RGPD_EXPORT = 'rgpd_export';

    /** Issue d'une action qui peut échouer (génération IA) : sert à en calculer le taux de réussite. */
    public const OUTCOME_SUCCESS = 'success';
    public const OUTCOME_FAILURE = 'failure';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    /** Compte concerné, ou null pour une visite non connectée (et après suppression du compte). */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    /** Rôle au moment du fait : « user », « prof », « admin » ou « anonyme ». */
    #[ORM\Column(length: 20)]
    private string $role = 'anonyme';

    /** Écran concerné, sous forme normalisée (« /quiz/:id »). */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $path = null;

    /** Ce sur quoi porte l'action : identifiant de quiz, catégorie… selon l'événement. */
    #[ORM\Column(length: 60, nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $outcome = null;

    /** Temps passé sur l'écran, en millisecondes (page_view uniquement). */
    #[ORM\Column(nullable: true)]
    private ?int $durationMs = null;

    public function __construct(
        #[ORM\Column(length: 40)]
        private string $name,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }

    // Accesseurs : lecture et modification des champs de l'entité.
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    /** Rattache le fait à un compte et recopie son rôle. */
    public function by(?User $user): self
    {
        $this->user = $user;
        $this->role = $user?->getRole() ?? 'anonyme';

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): self
    {
        $this->path = null === $path ? null : self::normalizePath($path);

        return $this;
    }

    /**
     * Remplace par « :id » tout segment d'URL qui n'est qu'un nombre : sans ça, chaque quiz
     * et chaque code PIN créerait sa propre ligne et le classement des écrans n'aurait aucun sens.
     */
    public static function normalizePath(string $path): string
    {
        $path = '/'.trim(parse_url(trim($path), PHP_URL_PATH) ?: '', '/');
        $path = (string) preg_replace('#/\d+#', '/:id', $path);

        return mb_substr($path, 0, 120);
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): self
    {
        $this->subject = null === $subject ? null : mb_substr($subject, 0, 60);

        return $this;
    }

    public function getOutcome(): ?string
    {
        return $this->outcome;
    }

    public function setOutcome(?string $outcome): self
    {
        $this->outcome = $outcome;

        return $this;
    }

    public function getDurationMs(): ?int
    {
        return $this->durationMs;
    }

    /** Durée bornée à une heure : un onglet laissé ouvert toute la nuit fausserait la moyenne. */
    public function setDurationMs(?int $durationMs): self
    {
        $this->durationMs = null === $durationMs ? null : max(0, min($durationMs, 3_600_000));

        return $this;
    }
}
