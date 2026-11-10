<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\InsufficientFundsException;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @property string $id
 * @property float $balance
 */
class Account extends Model
{
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'balance',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'balance' => 0,
    ];

    /**
     * Increment the account balance by a strictly positive amount.
     *
     * @throws InvalidArgumentException
     */
    public function deposit(int|float $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Deposit amount must be strictly positive.');
        }

        $this->balance += $amount;
    }

    /**
     * Decrement the account balance by a strictly positive amount if sufficient funds exist.
     *
     * @throws InvalidArgumentException
     * @throws InsufficientFundsException
     */
    public function withdraw(int|float $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Withdrawal amount must be strictly positive.');
        }

        if ($this->balance < $amount) {
            throw new InsufficientFundsException('Insufficient funds for withdrawal.');
        }

        $this->balance -= $amount;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance' => 'float',
        ];
    }
}
