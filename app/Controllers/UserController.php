<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Validation\ValidationException;
use App\Validation\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as V;

class UserController extends BaseController
{
    /**
     * List all users
     */
    public function index(Request $request, Response $response): Response
    {
        $users = User::all();

        return $this->json($response, [
            'users' => $users,
        ]);
    }

    /**
     * Get a single user
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $user = User::find($id);

        if (!$user) {
            return $this->notFound($response, 'User not found');
        }

        return $this->json($response, [
            'user' => $user,
        ]);
    }

    /**
     * Create a new user
     */
    public function create(Request $request, Response $response): Response
    {
        $data = $this->getBody($request);

        try {
            // Validate input
            $this->validate($data, [
                'name' => Validator::string(3, 255),
                'email' => V::allOf(
                    Validator::email(),
                    Validator::unique('users', 'email')
                ),
                'password' => Validator::password(8),
            ]);

            // Create user
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            ]);

            return $this->json($response, [
                'message' => 'User created successfully',
                'user' => $user,
            ], 201);

        } catch (ValidationException $e) {
            return $this->validationError($response, $e);
        }
    }

    /**
     * Update a user
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $user = User::find($id);

        if (!$user) {
            return $this->notFound($response, 'User not found');
        }

        $data = $this->getBody($request);

        try {
            // Validate input (email unique except current user)
            $this->validate($data, [
                'name' => Validator::string(3, 255),
                'email' => V::allOf(
                    Validator::email(),
                    Validator::unique('users', 'email', $id)
                ),
            ]);

            // Update user
            $user->name = $data['name'];
            $user->email = $data['email'];

            if (isset($data['password']) && $data['password'] !== '') {
                // If password is provided, validate and update
                $this->validate($data, [
                    'password' => Validator::password(8),
                ]);
                $user->password = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            $user->save();

            return $this->json($response, [
                'message' => 'User updated successfully',
                'user' => $user,
            ]);

        } catch (ValidationException $e) {
            return $this->validationError($response, $e);
        }
    }

    /**
     * Delete a user
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $user = User::find($id);

        if (!$user) {
            return $this->notFound($response, 'User not found');
        }

        $user->delete();

        return $this->json($response, [
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Verify user email
     */
    public function verifyEmail(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $user = User::find($id);

        if (!$user) {
            return $this->notFound($response, 'User not found');
        }

        if ($user->isEmailVerified()) {
            return $this->json($response, [
                'message' => 'Email already verified',
            ]);
        }

        $user->markEmailAsVerified();

        return $this->json($response, [
            'message' => 'Email verified successfully',
            'user' => $user,
        ]);
    }
}
