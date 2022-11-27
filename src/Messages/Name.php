<?php

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerName;
use Illuminate\Database\Eloquent\Model;

class Name extends Message
{
    public string $language;
    public string $name;
    public string $ownerUuid;

    public function __construct(
        ?string $name = null,
        ?string $language = null,
        ?string $ownerUuid = null
    ) {
        if ($language !== null) {
            $this->language = $language;
        }
        if ($name !== null) {
            $this->name = $name;
        }
        if ($ownerUuid !== null) {
            $this->ownerUuid = $ownerUuid;
        }
    }

    /**
     * Add, update, or delete this name from/to a model
     * @param Model $owner
     * @return void
     */
    public function applyTo(Model $owner)
    {
        /** @noinspection PhpPossiblePolymorphicInvocationInspection */
        $ledgerName = $owner->names->firstWhere('language', $this->language);
        if ($this->name === '') {
            if ($ledgerName !== null) {
                $ledgerName->delete();
            }
        } else {
            if ($ledgerName === null) {
                $ledgerName = new LedgerName();
                $ledgerName->ownerUuid = $owner->getKey();
                $ledgerName->language = $this->language;
            }
            $ledgerName->name = $this->name;
            $ledgerName->save();
        }
    }

    /**
     * @inheritdoc
     */
    public static function fromArray(array $data, int $opFlags = self::OP_ADD): self
    {
        $name = new static();
        $name->name = $data['name'] ?? '';
        if (isset($data['language'])) {
            $name->language = $data['language'];
        }
        if ($opFlags & self::F_VALIDATE) {
            $name->validate($opFlags);
        }

        return $name;
    }

    /**
     * Populate an array of names with request data.
     *
     * @param array $data Data generated by the request.
     * @param int $opFlags Bitmask of the request operation (may include FM_VALIDATE)
     * @param int $minimum the minimum number of elements that should be present.
     * @return Name[]
     * @throws Breaker
     */
    public static function fromRequestList(array $data, int $opFlags, int $minimum = 0): array
    {
        $names = [];
        foreach ($data as $nameData) {
            $message = self::fromArray($nameData, $opFlags);
            $names[$message->language ?? ''] = $message;
        }
        if (count($names) < $minimum) {
            $entry = $minimum === 1 ? 'entry' : 'entries';
            throw Breaker::withCode(
                Breaker::BAD_REQUEST, ["must provide at least $minimum name $entry"]
            );
        }

        return $names;
    }

    /**
     * @inheritdoc
     */
    public function validate(int $opFlags = 0): self
    {
        if ($this->name === '' && !($opFlags & self::OP_UPDATE)) {
            throw Breaker::withCode(
                Breaker::RULE_VIOLATION, [__("Must include name property.")]
            );
        }
        $rules = LedgerAccount::rules(
            bootable: $opFlags & self::OP_CREATE
        );
        $this->language ??= $rules->language->default;
        if ($this->language === '') {
            throw Breaker::withCode(
                Breaker::RULE_VIOLATION, [__("Language cannot be empty.")]
            );
        }
        // If the name is empty here, then this is an update.
        if (
            $this->name === ''
            && $this->language === $rules->language->default
        ) {
            throw Breaker::withCode(
                Breaker::RULE_VIOLATION,
                [__("Cannot delete name in default language.")]
            );
        }

        return $this;
    }
}
