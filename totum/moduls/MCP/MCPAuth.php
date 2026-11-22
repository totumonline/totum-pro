<?php

namespace totum\moduls\MCP;

use Psr\Http\Message\ServerRequestInterface;
use totum\common\Auth;
use totum\common\errorException;
use totum\common\User;
use totum\config\Conf;

class MCPAuth
{
    public const INTERFACE = 'agent';
    public const UNAUTHORIZED = 401;
    public const FORBIDDEN = 403;
    /* HTTP header of the creator view (admin layer); stdio — option --creator=true */
    public const CREATOR_HEADER = 'TTM-Creator';

    /**
     * Value of --creator / TTM-Creator: true, 1, yes, on (the option without a value — null — is on too)
     */
    public static function isFlagOn(mixed $value): bool
    {
        return $value === null || $value === true || in_array(mb_strtolower(trim((string)$value)), ['true', '1', 'yes', 'on'], true);
    }

    /**
     * The creator view does not give rights: it is opened only for users with the role Creator
     */
    public static function checkCreator(User $User): void
    {
        if (!$User->isCreator()) {
            throw new errorException('Creator view is available only for users with the role Creator', static::FORBIDDEN);
        }
    }

    /**
     * HTTP: login/password from "Authorization: Basic" header, checked on every request
     */
    public static function byRequest(Conf $Config, ServerRequestInterface $request): User
    {
        $header = $request->getHeaderLine('Authorization');
        if (!preg_match('/^Basic\s+(.+)$/i', $header, $match)
            || !($decoded = base64_decode(trim($match[1]), true))
            || !str_contains($decoded, ':')) {
            throw new errorException('Authorization required: use "Authorization: Basic base64(login:password)"', static::UNAUTHORIZED);
        }
        [$login, $password] = explode(':', $decoded, 2);
        return static::byPassword($Config, $login, $password);
    }

    public static function byPassword(Conf $Config, string $login, string $password): User
    {
        if ($login === '' || $password === '') {
            throw new errorException('Login and password are required', static::UNAUTHORIZED);
        }
        $userRow = Auth::getUserRowWithServiceRestriction(mb_strtolower($login), $Config, static::INTERFACE);
        if ($userRow && !is_string($userRow['pass'] ?? null)) {
            /* user without password can't be authorized by password (core expects string hash) */
            $userRow = false;
        }
        switch (Auth::passwordCheckingAndProtection($login, $password, $userRow, $Config, static::INTERFACE)) {
            case Auth::$AuthStatuses['OK']:
                return static::loadUser($Config, $userRow['id']);
            case Auth::$AuthStatuses['BLOCKED_BY_CRACKING_PROTECTION']:
                throw new errorException($Config->getLangObj()->translate('Due to exceeding the number of password attempts, your IP is blocked'),
                    static::UNAUTHORIZED);
            default:
                throw new errorException('User with interface "' . static::INTERFACE . '" and this login/password is not found', static::UNAUTHORIZED);
        }
    }

    /**
     * CLI (stdio): shell access means full access, so only login or email is required
     */
    public static function byLogin(Conf $Config, string $loginOrEmail): User
    {
        if ($loginOrEmail === ''
            || !($userRow = Auth::getUserRowWithServiceRestriction(mb_strtolower($loginOrEmail), $Config, static::INTERFACE))) {
            throw new errorException('User with interface "' . static::INTERFACE . '" and login/email "' . $loginOrEmail . '" is not found',
                static::UNAUTHORIZED);
        }
        return static::loadUser($Config, $userRow['id']);
    }

    protected static function loadUser(Conf $Config, $userId): User
    {
        $User = Auth::getUserById($Config, $userId);
        if (!$User) {
            throw new errorException('User is not found', static::UNAUTHORIZED);
        }
        return $User;
    }
}
