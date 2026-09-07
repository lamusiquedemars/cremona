<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\CrmTask;
use App\Models\IncomingRequest;
use App\Models\Person;
use App\Models\PrivateDocument;
use App\Models\PrivateDocumentLink;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

class PrivateDocumentManager
{
    public const LINK_FIELDS = [
        'person_ids' => Person::class,
        'company_ids' => Company::class,
        'incoming_request_ids' => IncomingRequest::class,
        'conversation_ids' => Conversation::class,
        'crm_task_ids' => CrmTask::class,
        'appointment_ids' => Appointment::class,
    ];

    public function __construct(private readonly OrganizationContext $context) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, UploadedFile $upload, User $uploader): PrivateDocument
    {
        return $this->store($data, $upload, $uploader);
    }

    /** @param array<string, mixed> $data */
    public function createVersion(PrivateDocument $previous, array $data, UploadedFile $upload, User $uploader): PrivateDocument
    {
        $this->assertOwned($previous);
        $data['version_of_id'] = $previous->getKey();
        $data['version_number'] = $previous->version_number + 1;

        foreach ($previous->links as $link) {
            $field = array_search(Relation::getMorphedModel($link->linkable_type), self::LINK_FIELDS, true);

            if ($field !== false) {
                $data[$field] ??= [];
                $data[$field][] = $link->linkable_id;
            }
        }

        return $this->store($data, $upload, $uploader);
    }

    /** @param array<string, mixed> $data */
    public function update(PrivateDocument $document, array $data): PrivateDocument
    {
        $this->assertOwned($document);
        $document->fill(Arr::only($data, ['title', 'category']));
        $document->save();
        $this->syncLinks($document, $data);

        return $document;
    }

    /** @return array<string, list<int>> */
    public function formLinks(PrivateDocument $document): array
    {
        $this->assertOwned($document);
        $links = array_fill_keys(array_keys(self::LINK_FIELDS), []);

        foreach ($document->links as $link) {
            $field = array_search(Relation::getMorphedModel($link->linkable_type), self::LINK_FIELDS, true);

            if ($field !== false) {
                $links[$field][] = $link->linkable_id;
            }
        }

        return $links;
    }

    /** @param array<string, mixed> $data */
    private function store(array $data, UploadedFile $upload, User $uploader): PrivateDocument
    {
        $organization = $this->context->require();
        $publicId = (string) Str::ulid();
        $extension = $upload->guessExtension() ?: $upload->getClientOriginalExtension();
        $filename = (string) Str::uuid().($extension !== '' ? '.'.strtolower($extension) : '');
        $path = 'documents/'.$organization->getKey().'/'.$publicId.'/'.$filename;

        if (! $upload->isValid() || $upload->getSize() === false || $upload->getRealPath() === false) {
            throw new LogicException('Le fichier à déposer est invalide.');
        }

        try {
            Storage::disk('local')->putFileAs(dirname($path), $upload, basename($path));

            return DB::transaction(function () use ($data, $upload, $uploader, $path, $publicId): PrivateDocument {
                $document = PrivateDocument::create(array_merge(
                    Arr::only($data, ['title', 'category', 'version_of_id', 'version_number']),
                    [
                        'public_id' => $publicId,
                        'uploaded_by_user_id' => $uploader->getKey(),
                        'disk' => 'local',
                        'path' => $path,
                        'original_name' => $upload->getClientOriginalName(),
                        'declared_mime_type' => $upload->getClientMimeType(),
                        'detected_mime_type' => $upload->getMimeType(),
                        'size' => $upload->getSize(),
                        'sha256' => hash_file('sha256', $upload->getRealPath()),
                    ],
                ));

                $this->syncLinks($document, $data);

                return $document;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    private function syncLinks(PrivateDocument $document, array $data): void
    {
        $this->assertOwned($document);
        $wanted = [];

        foreach (self::LINK_FIELDS as $field => $model) {
            $ids = array_values(array_unique(array_map('intval', (array) ($data[$field] ?? []))));

            if ($ids === []) {
                continue;
            }

            $found = $model::query()->whereKey($ids)->pluck('id')->map(fn ($id): int => (int) $id)->all();

            if (count($found) !== count($ids)) {
                throw new LogicException('Un rattachement sélectionné ne relève pas de l’organisation active.');
            }

            foreach ($found as $id) {
                $wanted[$this->morphAlias($model).':'.$id] = [$this->morphAlias($model), $id];
            }
        }

        $document->links()->get()->each(function (PrivateDocumentLink $link) use ($wanted): void {
            $key = $link->linkable_type.':'.$link->linkable_id;

            if (! isset($wanted[$key])) {
                $link->delete();
            }
        });

        foreach ($wanted as [$type, $id]) {
            $document->links()->firstOrCreate([
                'organization_id' => $document->organization_id,
                'linkable_type' => $type,
                'linkable_id' => $id,
            ]);
        }
    }

    private function assertOwned(PrivateDocument $document): void
    {
        if ((int) $document->organization_id !== (int) $this->context->require()->getKey()) {
            throw new LogicException('Le document privé ne relève pas de l’organisation active.');
        }
    }

    /** @param class-string $model */
    private function morphAlias(string $model): string
    {
        return array_search($model, PrivateDocumentLink::LINKABLE_MODELS, true)
            ?: throw new LogicException('Type de rattachement non autorisé.');
    }
}
