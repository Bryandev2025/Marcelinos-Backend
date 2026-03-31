<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use App\Models\ActivityLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->poll('5s')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('description')
                    ->label('')
                    ->html()
                    ->searchable()
                    ->formatStateUsing(function (ActivityLog $record): string {
                        $actorName = $record->user?->name ?? 'System';
                        $actorLabel = auth()->id() === $record->user_id ? 'You' : $actorName;
                        $timeAgo = $record->created_at?->diffForHumans() ?? '';
                        $message = self::displayMessage($record);

                        return sprintf(
                            '<div class="leading-tight">
                                <div class="text-[22px] font-medium text-gray-900 dark:text-gray-100">%s</div>
                                <div class="mt-1 text-[18px] text-gray-600 dark:text-gray-400">%s | %s</div>
                            </div>',
                            e($message),
                            e($actorLabel),
                            e($timeAgo)
                        );
                    }),

                TextColumn::make('created_at')
                    ->label('')
                    ->formatStateUsing(fn (ActivityLog $record): string => $record->created_at?->format('H:i m/d/y') ?? '-')
                    ->alignEnd()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(fn (): array => ActivityLog::query()
                        ->select('category')
                        ->whereNotNull('category')
                        ->distinct()
                        ->orderBy('category')
                        ->pluck('category')
                        ->filter()
                        ->mapWithKeys(fn (string $category): array => [
                            $category => Str::headline($category),
                        ])
                        ->all()),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user:id,name'));
    }

    private static function displayMessage(ActivityLog $record): string
    {
        $message = self::stripActorPrefix($record);

        if ($record->category === 'auth' && $record->event === 'user.login') {
            return 'logged in.';
        }

        if ($record->category === 'auth' && $record->event === 'user.logout') {
            return 'logged out.';
        }

        if ($record->category === 'review' && $record->event === 'review.approval_changed') {
            return (bool) data_get($record->meta, 'is_approved') ? 'approved a review.' : 'unapproved a review.';
        }

        if ($record->category === 'resource') {
            return self::resourceMessage($record, $message);
        }

        if (str_starts_with($message, 'Unknown user ')) {
            $message = ltrim(substr($message, strlen('Unknown user')));
        }

        return $message;
    }

    private static function resourceMessage(ActivityLog $record, string $message): string
    {
        if (preg_match('/^([A-Za-z0-9_\\\\]+)\s(created|updated|deleted):\s(.+)\.$/i', $message, $matches) !== 1) {
            return $message;
        }

        $modelName = self::humanizeModelName((string) $matches[1]);
        $verb = strtolower((string) $matches[2]);
        $subject = trim((string) $matches[3]);

        if ($modelName === 'gallery') {
            return match ($verb) {
                'created' => 'added gallery media.',
                'updated' => 'updated gallery media.',
                'deleted' => 'deleted gallery media.',
                default => sprintf('%s %s: %s.', $verb, $modelName, $subject),
            };
        }

        return sprintf('%s %s: %s.', $verb, $modelName, $subject);
    }

    private static function humanizeModelName(string $model): string
    {
        return strtolower(Str::headline(class_basename($model)));
    }

    private static function stripActorPrefix(ActivityLog $record): string
    {
        $message = trim((string) $record->description);
        $actor = trim((string) ($record->user?->name ?? ''));

        if ($actor !== '' && str_starts_with($message, $actor . ' ')) {
            return ltrim(substr($message, strlen($actor)));
        }

        return $message;
    }
}
