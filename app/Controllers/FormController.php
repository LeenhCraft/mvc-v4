<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Validation\ValidationException;
use App\Validation\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as V;

/**
 * Form Controller
 *
 * Examples of forms with CSRF protection
 */
class FormController extends BaseController
{
    /**
     * Show contact form
     */
    public function showContactForm(Request $request, Response $response): Response
    {
        return $this->render($request, $response, 'forms/contact.twig', [
            'title' => 'Contact Form',
        ]);
    }

    /**
     * Handle contact form submission
     */
    public function submitContactForm(Request $request, Response $response): Response
    {
        $data = $this->getBody($request);

        try {
            $this->validate($data, [
                'name' => Validator::string(3, 100),
                'email' => Validator::email(),
                'subject' => Validator::string(5, 200),
                'message' => Validator::string(10, 1000),
            ]);

            // Process form (e.g., send email, save to database)
            // ...

            // Success - redirect with flash message
            return $this->render($request, $response, 'forms/contact.twig', [
                'title' => 'Contact Form',
                'success' => 'Thank you! Your message has been sent successfully.',
                'submitted_data' => $data,
            ]);

        } catch (ValidationException $e) {
            return $this->render($request, $response, 'forms/contact.twig', [
                'title' => 'Contact Form',
                'errors' => $e->getErrors(),
                'old' => $data,
            ]);
        }
    }

    /**
     * Show registration form
     */
    public function showRegisterForm(Request $request, Response $response): Response
    {
        return $this->render($request, $response, 'forms/register.twig', [
            'title' => 'User Registration',
        ]);
    }

    /**
     * Handle registration form submission
     */
    public function submitRegisterForm(Request $request, Response $response): Response
    {
        $data = $this->getBody($request);

        try {
            $this->validate($data, [
                'name' => Validator::string(3, 100),
                'email' => V::allOf(
                    Validator::email(),
                    Validator::unique('users', 'email')
                ),
                'password' => Validator::password(8),
                'password_confirmation' => Validator::password(8),
            ]);

            // Check passwords match
            if ($data['password'] !== $data['password_confirmation']) {
                throw new ValidationException([
                    'password_confirmation' => ['Passwords do not match'],
                ]);
            }

            // Create user
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            ]);

            return $this->render($request, $response, 'forms/register.twig', [
                'title' => 'User Registration',
                'success' => 'Registration successful! Welcome, ' . $user->name,
                'user' => $user,
            ]);

        } catch (ValidationException $e) {
            return $this->render($request, $response, 'forms/register.twig', [
                'title' => 'User Registration',
                'errors' => $e->getErrors(),
                'old' => $data,
            ]);
        }
    }
}
