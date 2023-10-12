<?php

declare(strict_types=1);

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Http\Controllers\JournalReferenceController;
use Abivia\Ledger\Models\JournalReference;
use Abivia\Ledger\Models\LedgerAccount;
use Exception;

class Reference extends Message
{
    use HasCodes;

    protected static array $copyable = [
        'code', 'extra',
        ['revision', self::OP_DELETE | self::OP_UPDATE],
        ['toCode', self::OP_UPDATE],
        'uuid',
    ];

    public EntityRef $domain;

    /**
     * @var mixed
     */
    public $extra;

    public string|int $journalReferenceUuid;

    /**
     * @var string Revision signature. Required for update.
     */
    public string $revision;

    /**
     * {@inheritdoc}
     */
    public static function fromArray(array $data, int $opFlags = self::OP_ADD): self
    {
        //        dd($data);
        $reference = new static();
        $reference->copy($data, $opFlags);
        if (isset($data['domain'])) {
            $reference->domain = EntityRef::fromMixed($data['domain']);
        }
        if (isset($data['uuid'])) {
            $reference->journalReferenceUuid = $data['uuid'];
        }
        if ($opFlags & self::F_VALIDATE) {
            $reference->validate($opFlags);
        }
        //        dd($reference);

        return $reference;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(int $opFlags = null): self
    {
        $opFlags ??= $this->getOpFlags();
        $errors = $this->validateCodes($opFlags, ['regEx' => '/.*/', 'uppercase' => false]);
        $rules = LedgerAccount::rules();

        if ($rules === null) {
            $errors[] = __('Ledger has not been initialized.');
        } else {
            if (! isset($this->domain)) {
                $this->domain = new EntityRef();
                $this->domain->code = $rules->domain->default;
            }
            $this->domain->validate(0);
        }
        //        dd($errors);
        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }

        return $this;
    }

    /**
     * Verify that the reference is valid, filling in the UUID if missing.
     *
     * @throws Breaker
     * @throws Exception
     */
    public function lookup(): self
    {
        /** @var JournalReference $journalReference */
        $journalReference = JournalReference::findWith($this)->first();
        //        dd($journalReference, $this);
        if ($journalReference === null) {
            throw Breaker::withCode(
                Breaker::BAD_REQUEST,
                [
                    __(
                        'Reference :code does not exist.',
                        ['code' => $this->code ?? '[undefined]']
                    ),
                ]
            );
        }
        if (! isset($this->journalReferenceUuid)) {
            //            dd($this, $journalReference->journalReferenceUuid);
            $this->journalReferenceUuid = $journalReference->journalReferenceUuid;
        }

        return $this;
    }

    /**
     * @throws Breaker
     */
    public function run(): array
    {
        $controller = new JournalReferenceController();
        $journalReference = $controller->run($this);
        if ($this->opFlags & Message::OP_DELETE) {
            $response['success'] = true;
        } else {
            try {
                $response['reference'] = $journalReference->toResponse();
            } catch (Exception $exception) {
                throw Breaker::withCode(
                    Breaker::SYSTEM_ERROR,
                    [__($exception->getMessage())]
                );
            }
        }

        return $response;
    }
}
