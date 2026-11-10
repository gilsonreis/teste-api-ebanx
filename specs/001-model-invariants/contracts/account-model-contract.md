# Contract: Account Domain Model & Exceptions

**Feature**: `001-model-invariants`  
**Scope**: Internal PHP Domain Layer Contract (`App\Models\Account` & `App\Exceptions\InsufficientFundsException`)

---

## 1. Class Contract: `App\Models\Account`

- **File**: `app/Models/Account.php`
- **Base Class**: `Illuminate\Database\Eloquent\Model`
- **Strict Types**: `declare(strict_types=1);`

### Public Signatures

```php
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
     * Increment the account balance by a strictly positive amount.
     *
     * @throws InvalidArgumentException If $amount <= 0
     */
    public function deposit(int|float $amount): void;

    /**
     * Decrement the account balance by a strictly positive amount if sufficient funds exist.
     *
     * @throws InvalidArgumentException If $amount <= 0
     * @throws InsufficientFundsException If $this->balance < $amount
     */
    public function withdraw(int|float $amount): void;
}
```

### Behavioral Postconditions

| Method | Precondition | Postcondition (Success) | Postcondition (Failure) |
| :--- | :--- | :--- | :--- |
| `deposit(int\|float $amount): void` | `$amount > 0` | `$this->balance` increases by `$amount` | Throws `\InvalidArgumentException`; `$this->balance` is unchanged |
| `withdraw(int\|float $amount): void` | `$amount > 0` and `$this->balance >= $amount` | `$this->balance` decreases by `$amount` | Throws `\InvalidArgumentException` (if `$amount <= 0`) or `InsufficientFundsException` (if `$this->balance < $amount`); `$this->balance` is unchanged |

---

## 2. Class Contract: `App\Exceptions\InsufficientFundsException`

- **File**: `app/Exceptions/InsufficientFundsException.php`
- **Base Class**: `\Exception`
- **Strict Types**: `declare(strict_types=1);`
- **Constraints**: Must not reference HTTP status codes, `Illuminate\Http\*`, or `Symfony\Component\HttpKernel\*`.

