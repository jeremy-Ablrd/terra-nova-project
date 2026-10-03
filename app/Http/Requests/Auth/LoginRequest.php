<?php

namespace App\Http\Requests\Auth;

use App\Services\SecuriteConnexion;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Tente la connexion. Limites et événements de sécurité : SecuriteConnexion (F37).
     * Le message de blocage est le même que l'adresse e-mail existe ou non.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $securite = app(SecuriteConnexion::class);
        $email = SecuriteConnexion::normaliser($this->input('email'));
        $this->merge(['email' => $email]);

        $this->ensureIsNotRateLimited($securite, $email);

        if (! Auth::attempt(['email' => $email, 'password' => (string) $this->input('password')], $this->boolean('remember'))) {
            $securite->echec($email, (string) $this->ip());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $securite->succes($email, (string) $this->ip());
    }

    /**
     * Refuse la tentative si une limite est atteinte.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(SecuriteConnexion $securite, string $email): void
    {
        $secondes = $securite->secondesRestantes($email, (string) $this->ip());

        if ($secondes <= 0) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'email' => trans_choice(
                '{1} Trop de tentatives de connexion. Réessayez dans :count minute.|[2,*] Trop de tentatives de connexion. Réessayez dans :count minutes.',
                max(1, (int) ceil($secondes / 60)),
            ),
        ]);
    }
}
