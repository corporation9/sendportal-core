<?php

declare(strict_types=1);

namespace Tests\Feature\API;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Sendportal\Base\Models\Campaign;
use Sendportal\Base\Models\Template;
use Sendportal\Base\Traits\NormalizeTags;
use Tests\TestCase;

class TemplatesControllerTest extends TestCase
{
    use RefreshDatabase,
        WithFaker,
        NormalizeTags;

    /** @test */
    public function the_template_index_is_accessible_to_authorised_users()
    {
        $user = $this->createUserWithWorkspace();

        $template = factory(Template::class)->create([
            'workspace_id' => $user->currentWorkspace()->id
        ]);

        $route = route('sendportal.api.templates.index', [
            'workspaceId' => $user->currentWorkspace()->id
        ]);

        $this
            ->actingAs($user, 'api')
            ->getJson($route)
            ->assertOk()
            ->assertJson([
                'data' => [
                    Arr::only($template->toArray(), ['id', 'name', 'content'])
                ],
            ]);
    }

    /** @test */
    public function a_single_template_is_accessible_to_authorised_users()
    {
        $user = $this->createUserWithWorkspace();

        $template = factory(Template::class)->create([
            'workspace_id' => $user->currentWorkspace()->id
        ]);

        $route = route('sendportal.api.templates.show', [
            'workspaceId' => $user->currentWorkspace()->id,
            'template' => $template->id
        ]);

        $this
            ->actingAs($user, 'api')
            ->getJson($route)
            ->assertOk()
            ->assertJson([
                'data' => Arr::only($template->toArray(), ['id', 'name', 'content']),
            ]);
    }

    /** @test */
    public function a_template_can_be_created_by_authorised_users()
    {
        $user = $this->createUserWithWorkspace();

        $route = route('sendportal.api.templates.store', $user->currentWorkspace()->id);

        $request = [
            'name' => $this->faker->name,
            'content' => 'Hello {{ content }}',
        ];

        $normalisedRequest = [
            'name' => $request['name'],
            'content' => $this->normalizeTags($request['content'], 'content')
        ];

        $this
            ->actingAs($user, 'api')
            ->postJson($route, $request)
            ->assertStatus(201)
            ->assertJson(['data' => $normalisedRequest]);

        $this->assertDatabaseHas('templates', $normalisedRequest);
    }

    /** @test */
    public function a_template_can_be_updated_by_authorised_users()
    {
        $user = $this->createUserWithWorkspace();

        $template = factory(Template::class)->create([
            'workspace_id' => $user->currentWorkspace()->id
        ]);

        $route = route('sendportal.api.templates.update', [
            'workspaceId' => $user->currentWorkspace()->id,
            'template' => $template->id
        ]);

        $request = [
            'name' => 'newName',
            'content' => 'newContent {{ content }}',
        ];

        $normalisedRequest = [
            'name' => $request['name'],
            'content' => $this->normalizeTags($request['content'], 'content')
        ];

        $this
            ->actingAs($user, 'api')
            ->putJson($route, $request)
            ->assertOk()
            ->assertJson(['data' => $normalisedRequest]);

        $this->assertDatabaseMissing('templates', $template->toArray());
        $this->assertDatabaseHas('templates', $normalisedRequest);
    }

    /** @test */
    public function a_template_can_be_deleted_by_authorised_users()
    {
        $user = $this->createUserWithWorkspace();

        $template = factory(Template::class)->create([
            'workspace_id' => $user->currentWorkspace()->id
        ]);

        $route = route('sendportal.api.templates.destroy', [
            'workspaceId' => $user->currentWorkspace()->id,
            'template' => $template->id
        ]);

        $this
            ->actingAs($user, 'api')
            ->deleteJson($route)
            ->assertStatus(204);

        $this->assertDatabaseMissing('templates', [
            'id' => $template->id
        ]);
    }

    /** @test */
    public function a_template_cannot_be_deleted_by_authorised_users_if_it_is_used()
    {
        $user = $this->createUserWithWorkspace();

        $template = factory(Template::class)->create([
            'workspace_id' => $user->currentWorkspace()->id
        ]);

        $campaign = factory(Campaign::class)->create([
            'template_id' => $template->id
        ]);

        $route = route('sendportal.api.templates.destroy', [
            'workspaceId' => $user->currentWorkspace()->id,
            'template' => $template->id
        ]);

        $this
            ->actingAs($user, 'api')
            ->deleteJson($route)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['template']);
    }

    /** @test */
    public function a_template_name_must_be_unique_for_a_workspace()
    {
        $user = $this->createUserWithWorkspace();

        $template = factory(Template::class)->create([
            'workspace_id' => $user->currentWorkspace()->id
        ]);

        $route = route('sendportal.api.templates.store', [
            'workspaceId' => $user->currentWorkspace()->id,
        ]);

        $request = [
            'name' => $template->name,
        ];

        $this
            ->actingAs($user, 'api')
            ->postJson($route, $request)
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->assertEquals(1, Template::where('name', $template->name)->count());
    }

    /** @test */
    public function two_workspaces_can_have_the_same_name_for_a_template()
    {
        $userA = $this->createUserWithWorkspace();
        $userB = $this->createUserWithWorkspace();

        $template = factory(Template::class)->create([
            'workspace_id' => $userA->currentWorkspace()->id
        ]);

        $route = route('sendportal.api.templates.store', [
            'workspaceId' => $userB->currentWorkspace()->id,
        ]);

        $request = [
            'name' => $template->name,
            'content' => 'newContent {{ content }}',
        ];

        $this
            ->actingAs($userB, 'api')
            ->postJson($route, $request)
            ->assertStatus(201);

        $this->assertEquals(2, Template::where('name', $template->name)->count());
    }
}
