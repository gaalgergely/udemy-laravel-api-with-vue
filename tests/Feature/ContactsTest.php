<?php

namespace Tests\Feature;

use App\Contact;
use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ContactsTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
    }

    /** @test */
    public function a_list_of_contacts_can_be_fetched_for_the_authenticated_user()
    {
        $user = factory(User::class)->create();
        $anotherUser = factory(User::class)->create();

        $contact = factory(Contact::class)->create(['user_id' => $user->id]);
        $anotherContact = factory(Contact::class)->create(['user_id' => $anotherUser->id]);

        $response = $this->get('/api/contacts?api_token=' . $user->api_token);

        $response->assertJsonCount(1)
            ->assertJson([
                'data' => [
                    [
                        'data' => [
                            'contact_id' => $contact->id
                        ]
                    ]
                ]
            ]);
    }

    /** @test */
    public function an_unauthenticated_user_should_redirected_to_login()
    {
        $response = $this->post('/api/contacts', array_merge($this->data(), ['api_token' => '']));

        $response->assertRedirect('/login');
        $this->assertCount(0, Contact::all());
    }

    /** @test */
    public function an_authenticated_user_can_add_a_contact()
    {
        $this->withoutExceptionHandling();

        $response = $this->post('/api/contacts', array_merge($this->data(), ['api_token' => $this->user->api_token]));

        $contact = Contact::first();

        $this->assertEquals('Test Name', $contact->name);
        $this->assertEquals('test@email.com', $contact->email);
        $this->assertEquals('05/14/1988', $contact->birthday->format('m/d/Y'));
        $this->assertEquals('ABC String', $contact->company);

        $response->assertStatus(Response::HTTP_CREATED);

        $response->assertJson([
            'data' => [
                'contact_id' => $contact->id
            ],
            'links' => [
                'self' => $contact->path()
            ]
        ]);
    }

    /** @test */
    public function fields_are_required()
    {
        collect(['name', 'email', 'birthday', 'company'])->each(function ($field) {

            $response = $this->post('/api/contacts', array_merge($this->data(), [$field => '']));

            $response->assertSessionHasErrors($field);

            $this->assertCount(0, Contact::all());
        });
    }

    /** @test */
    public function email_must_be_a_valid_email()
    {
        $response = $this->post('/api/contacts', array_merge($this->data(), ['email' => 'NOT AN EMAIL']));

        $response->assertSessionHasErrors('email');

        $this->assertCount(0, Contact::all());
    }

    /** @test */
    public function birthdays_are_properly_stored()
    {
        $this->withoutExceptionHandling();

        $this->post('/api/contacts', array_merge($this->data()), ['birthday' => 'May 14, 1988']);

        $this->assertCount(1, Contact::all());

        $birthday = Contact::first()->birthday;

        $this->assertInstanceOf(Carbon::class, $birthday);
        $this->assertEquals('05/14/1988', $birthday->format('m/d/Y'));
    }

    /** @test */
    public function a_contact_can_be_retrieved()
    {
        $this->withoutExceptionHandling();

        $contact = factory(Contact::class)->create(['user_id' => $this->user->id]);

        $response = $this->get('/api/contacts/' . $contact->id . '?api_token=' . $this->user->api_token);

        $response->assertJson([
            'data' => [
                'name' => $contact->name,
                'email' => $contact->email,
                'birthday' => $contact->birthday->format('m/d/Y'),
                'company' => $contact->company
            ]
        ]);
    }

    /** @test */
    public function only_the_users_contacts_can_be_retreived()
    {
        $contact = factory(Contact::class)->create(['user_id' => $this->user->id]);

        $user = factory(User::class)->create();

        $response = $this->get('/api/contacts/' . $contact->id . '?api_token=' . $user->api_token);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /** @test */
    public function a_contact_can_be_updated()
    {
        $this->withoutExceptionHandling();

        $contact = factory(Contact::class)->create(['user_id' => $this->user->id]);

        $response = $this->put('/api/contacts/' . $contact->id, $this->data());

        $contact = $contact->fresh();

        $this->assertEquals('Test Name', $contact->name);
        $this->assertEquals('test@email.com', $contact->email);
        $this->assertEquals('05/14/1988', $contact->birthday->format('m/d/Y'));
        $this->assertEquals('ABC String', $contact->company);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJson([
            'data' => [
                'contact_id' => $contact->id
            ],
            'links' => [
                'self' => $contact->path()
            ]
        ]);
    }

    /** @test */
    public function only_the_owner_can_update_the_contact()
    {
        $contact = factory(Contact::class)->create(['user_id' => $this->user->id]);

        $user = factory(User::class)->create();

        $response = $this->put('/api/contacts/' . $contact->id, array_merge($this->data(), ['api_token' => $user->api_token]));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /** @test */
    public function a_contact_can_be_deleted()
    {
        $contact = factory(Contact::class)->create(['user_id' => $this->user->id]);

        $response = $this->delete('/api/contacts/' . $contact->id, ['api_token' => $this->user->api_token]);

        $this->assertCount(0, Contact::all());

        $response->assertStatus(Response::HTTP_NO_CONTENT);
    }

    /** @test */
    public function only_the_owner_can_delete_the_contact()
    {
        $contact = factory(Contact::class)->create(['user_id' => $this->user->id]);

        $user = factory(User::class)->create();

        $response = $this->delete('/api/contacts/' . $contact->id, ['api_token' => $user->api_token]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    private function data()
    {
        return [
            'name' => 'Test Name',
            'email' => 'test@email.com',
            'birthday' => '05/14/1988',
            'company' => 'ABC String',
            'api_token' => $this->user->api_token
        ];
    }
}
