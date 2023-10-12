<?php

namespace Abivia\Ledger\Models;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Messages\EntityRef;
use Abivia\Ledger\Messages\SubJournal as JournalMessage;
use Abivia\Ledger\Traits\CommonResponseProperties;
use Abivia\Ledger\Traits\HasRevisions;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\HigherOrderCollectionProxy;

/**
 * Domains assigned within the ledger.
 *
 * @method static SubJournal create(array $attributes) Provided by model.
 *
 * @property string|int $code Unique identifier for the sub-journal.
 * @property Carbon $created_at When the record was created.
 * @property string $extra Application defined information.
 * @property LedgerName[] $names
 * @property Carbon $revision Revision timestamp to detect race condition on update.
 * @property string $subJournalUuid Identifier for this journal.
 * @property Carbon $updated_at When the record was updated.
 *
 * @mixin Builder
 */
class SubJournal extends Model
{
    use CommonResponseProperties, HasFactory, HasRevisions;

    //    public $incrementing = false;

    public $primaryKey = 'subJournalUuid';

    protected $casts = [
        'revision' => 'datetime',
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['code', 'extra'];

    protected $keyType = 'int';

    public static function createFromMessage(JournalMessage $message): self
    {
        $instance = new static();
        foreach ($instance->fillable as $property) {
            if (isset($message->{$property})) {
                $instance->{$property} = $message->{$property};
            }
        }
        $instance->save();
        //        dd($instance);
        $instance->refresh();

        return $instance;
    }

    /**
     * @throws Breaker
     *
     * @noinspection PhpIncompatibleReturnTypeInspection
     * @noinspection PhpDynamicAsStaticMethodCallInspection
     */
    public static function findWith(EntityRef $entityRef): Builder
    {
        if (isset($entityRef->uuid) && $entityRef->uuid !== null) {
            $finder = self::where('domainUuid', $entityRef->uuid);
        } elseif (isset($entityRef->code)) {
            $finder = self::where('code', $entityRef->code);
        } else {
            throw Breaker::withCode(
                Breaker::INVALID_DATA,
                [__('Journal reference must have either code or uuid entries')]
            );
        }

        return $finder;
    }

    protected static function booted()
    {
        static::saved(function ($model) {
            $model->clearRevisionCache();
        });
    }

    /**
     * The revision Hash is computationally expensive, only calculated when required.
     *
     * @return HigherOrderCollectionProxy|mixed|string|null
     *
     * @throws Exception
     */
    public function __get($key)
    {
        if ($key === 'revisionHash') {
            return $this->getRevisionHash();
        }

        return parent::__get($key);
    }

    public function names(): HasMany
    {
        return $this->hasMany(LedgerName::class, 'ownerUuid', 'subJournalUuid');
    }

    public function toResponse(): array
    {
        $response = ['uuid' => $this->subJournalUuid];
        $response['code'] = $this->code;

        return $this->commonResponses($response);
    }
}
