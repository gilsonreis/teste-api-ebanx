# Quickstart & Validation Guide: SPEC-02 (Account Repository & Pluggable Persistence)

**Feature**: `002-repository-persistence`  
**Spec**: `specs/002-repository-persistence/spec.md`  
**Data Model**: `specs/002-repository-persistence/data-model.md`  
**Contract**: `specs/002-repository-persistence/contracts/account-repository-contract.md`

---

## 1. Pré-requisitos

- PHP `^8.3` com extensão `pdo_sqlite` habilitada.
- Dependências do Composer instaladas (`composer install`).
- Entidade `App\Models\Account` implementada (`SPEC-01`).

---

## 2. Comandos de Validação e Execução

Após a implementação das tarefas da `SPEC-02`, execute os comandos abaixo na raiz do projeto para validar todos os critérios de aceitação:

### 2.1 Executar os Testes Unitários dos Repositórios

```bash
php artisan test --filter=RepositoryTest
```

**Resultado esperado**:
- `PASS Tests\Unit\Repositories\CacheAccountRepositoryTest`
- `PASS Tests\Unit\Repositories\EloquentAccountRepositoryTest`
- `PASS Tests\Unit\Repositories\RedisAccountRepositoryTest`
- Cobertura integral dos cenários Gherkin para os 3 drivers (`cache`, `database`, `redis`):
  1. Salvar conta (`id: "100"`, `balance: 20`) e recuperar com `find("100")` retornando saldo `20.0`.
  2. Buscar conta inexistente (`find("999")`) retornando `null` sem efeitos colaterais.
  3. Executar `reset()` com múltiplas contas salvas (`"100"` e `"200"`) e confirmar que buscas subsequentes retornam `null`.
  4. Configurar `account.driver` com `"invalid_driver"` e confirmar que `app(AccountRepositoryInterface::class)` lança `\InvalidArgumentException`.

### 2.2 Validar Cache de Configuração do Laravel (`config:cache`)

```bash
php artisan config:cache
php artisan config:clear
```

**Resultado esperado**:
- `Configuration cached successfully.` seguido de `Configuration cache cleared successfully.`, comprovando que nenhuma chamada `env()` existe fora de `config/` e que `config/account.php` é serializável para produção.

### 2.3 Executar Toda a Suíte de Testes da Aplicação

```bash
php artisan test
```

**Resultado esperado**:
- Todos os testes unitários (`AccountTest` + `CacheAccountRepositoryTest` + `EloquentAccountRepositoryTest` + `RedisAccountRepositoryTest`) e de feature passando sem erros.

### 2.4 Validar Padronização de Estilo (Laravel Pint) e Análise Estática (PHPStan Nível 6)

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

**Resultado esperado**:
- `PASS` no Laravel Pint e `[OK] No errors` no PHPStan/Larastan nível 6.

