---
name: laravel-architecture
description: >-
  Guide for structuring Laravel applications. Use this skill when the user asks about
  architecture, project structure, where to put code, how to organize features, or
  when deciding between services vs actions, repositories vs Eloquent, events vs
  observers. Triggers on: "where should I put this", "how to structure", "architecture",
  "design pattern", "service class", "action class", "repository pattern", "refactor
  for maintainability", or when building a new feature that spans multiple layers.
---

# Laravel Architecture

How to structure Laravel applications so they stay maintainable as they grow. Start simple, add structure only when complexity demands it.

## The Default: Start with Laravel's Structure

For most applications, Laravel's default directory structure is enough. Don't add services, repositories, or actions until you have a reason.

```
app/
├── Http/Controllers/     # Receives requests, returns responses
├── Http/Requests/        # Validation rules
├── Models/               # Eloquent models, relationships, scopes
├── Policies/             # Authorization logic
└── Enums/                # Status types, fixed value sets
```

This handles a surprising amount of complexity. A controller that calls a model directly is not "bad architecture" — it's appropriate architecture for simple operations.

## When to Extract: The Decision Framework

### Keep it in the controller when:
- The operation is a simple CRUD action
- There's no logic beyond validate → create/update → respond
- Only one controller needs this behavior

### Extract to a service when:
- Multiple controllers or commands need the same operation
- The operation involves coordinating multiple models or side effects
- The logic is complex enough that testing the controller becomes painful

### Extract to an action when:
- The operation is a single, well-defined task ("CreateProject", "CancelOrder")
- You want each action to be independently testable
- The naming makes the codebase more readable

Actions and services solve the same problem differently. Actions are single-purpose (one public method). Services group related operations. Pick one pattern per project — mixing both creates confusion.

## Services

A service encapsulates business logic that doesn't belong in a controller or model. It coordinates between models, sends notifications, dispatches jobs.

```php
final class ProjectService
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function create(CreateProjectData $data, User $owner): Project
    {
        $project = Project::create([
            'name' => $data->name,
            'owner_id' => $owner->id,
        ]);

        $this->notifications->projectCreated($project);

        return $project;
    }

    public function archive(Project $project): void
    {
        $project->update(['status' => ProjectStatus::Archived]);

        $project->members->each->notify(new ProjectArchived($project));
    }
}
```

### Guidelines for services:
- Inject dependencies through the constructor
- Each method does one thing and returns data or void — not both
- Services call models, not other services (avoid service chains)
- Name methods after what they do, not how they're called

## Actions

An action is a single-purpose class with one public method. The class name IS the documentation.

```php
final class CreateProject
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function handle(CreateProjectData $data, User $owner): Project
    {
        $project = Project::create([
            'name' => $data->name,
            'owner_id' => $owner->id,
        ]);

        $this->notifications->projectCreated($project);

        return $project;
    }
}
```

```php
// In the controller
public function __invoke(StoreProjectRequest $request, CreateProject $action): JsonResponse
{
    $project = $action->handle(
        CreateProjectData::fromRequest($request),
        $request->user(),
    );

    return ProjectResource::make($project)->response()->setStatusCode(201);
}
```

## Repositories: Usually Not Needed

Eloquent IS your repository. Wrapping it in an interface adds indirection without value in most Laravel applications.

**When repositories make sense:**
- You genuinely need to swap data sources (Eloquent → API → file)
- You're building a package that shouldn't depend on Eloquent
- Your queries are complex enough to warrant dedicated query classes

**When they don't:**
- "For testability" — you can mock Eloquent models directly
- "For clean architecture" — Laravel's architecture IS the architecture, don't fight it
- "Because it's best practice" — it's a practice, not always the best one

If you need reusable queries, use Eloquent scopes or dedicated query builder classes instead of full repository abstractions.

## Events vs Observers vs Direct Calls

### Direct calls (default)
When A causes B and that relationship is obvious, just call B directly. A service method that creates a project and sends a notification is clear and debuggable.

### Events
When A happens and multiple independent things should react, use events. The key word is "independent" — if the listeners have ordering dependencies, events are the wrong tool.

```php
// Good use of events — independent reactions
ProjectCreated::dispatch($project);

// Listener 1: Send welcome email
// Listener 2: Update analytics
// Listener 3: Create audit log
```

### Observers
When you need to react to model lifecycle events (creating, updating, deleting) regardless of where in the codebase the model is modified. Observers are implicit — they fire automatically, which makes them harder to trace when debugging.

Use observers sparingly. If you need to set a UUID on creation, an observer works. If you need to send an email on creation, a direct call in the service is clearer.

## Jobs and Queues

Move slow operations out of the request cycle. If it takes more than a second or involves external services, it should probably be a queued job.

```php
final class ProcessProjectImport implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $projectId,
        private readonly string $filePath,
    ) {}

    public function handle(ImportService $service): void
    {
        $project = Project::findOrFail($this->projectId);
        $service->import($project, $this->filePath);
    }
}
```

### Queue guidelines:
- Pass IDs, not models — models may change between dispatch and execution
- Implement `ShouldQueue` for async, omit it for sync
- Add `tries`, `backoff`, and `failed()` for resilience
- Don't queue things that the user needs to see immediately

## Scaling Patterns

As the application grows, add structure incrementally:

**Small app** (10-20 models): Controllers + Models + Form Requests. No services needed.

**Medium app** (20-50 models): Extract services or actions for complex operations. Add DTOs for data passing between layers. Events for cross-cutting concerns.

**Large app** (50+ models): Consider domain-based organization:
```
app/
├── Domain/
│   ├── Projects/
│   │   ├── Models/
│   │   ├── Services/
│   │   ├── Events/
│   │   └── Data/
│   └── Billing/
│       ├── Models/
│       ├── Services/
│       └── Jobs/
├── Http/Controllers/
└── Http/Requests/
```

Don't reorganize preemptively. Reorganize when navigation becomes painful — that's the signal, not a model count.

**Migration cost warning:** Restructuring into domain folders means updating namespaces in every model, relation closure, factory, policy binding, and morph map. For 50+ models, that's a real migration with real risk of breakage. Only do it when the pain of the current structure exceeds the cost of the migration.
