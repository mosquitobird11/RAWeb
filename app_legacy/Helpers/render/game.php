<?php

use Illuminate\Support\Facades\Cache;
use LegacyApp\Site\Enums\Permissions;

function gameAvatar(
    int|string|array $game,
    ?bool $label = null,
    bool|string|null $icon = null,
    int $iconSize = 32,
    string $iconClass = 'badgeimg',
    bool|string|array $tooltip = true,
    ?string $context = null,
): string {
    $id = $game;
    $title = null;

    if (is_array($game)) {
        $id = $game['GameID'] ?? $game['ID'];

        if ($label !== false) {
            $title = $game['GameTitle'] ?? $game['Title'] ?? null;
            $consoleName = $game['Console'] ?? $game['ConsoleName'] ?? null;
            if ($consoleName) {
                $title .= " ($consoleName)";
            }
            sanitize_outputs($title);   // sanitize before rendering HTML
            $label = renderGameTitle($title);
        }

        if ($icon === null) {
            $icon = media_asset($game['GameIcon'] ?? $game['ImageIcon']);
        }

        // pre-render tooltip
        if (!is_string($tooltip)) {
            $tooltip = $tooltip !== false ? $game : false;
        }
    }

    return avatar(
        resource: 'game',
        id: $id,
        label: $label !== false && ($label || !$icon) ? $label : null,
        link: route('game.show', $id),
        tooltip: is_array($tooltip) ? renderGameCard($tooltip) : $tooltip,
        iconUrl: $icon !== false && ($icon || !$label) ? $icon : null,
        iconSize: $iconSize,
        iconClass: $iconClass,
        context: $context,
        sanitize: $title === null,
        altText: $title ?? (is_string($label) ? $label : null),
    );
}

/**
 * Render game title, wrapping categories for styling
 */
function renderGameTitle(?string $title = null, bool $tags = true): string
{
    $title ??= '';

    // Update $html by appending text
    $updateHtml = function (&$html, $text, $append) {
        $html = trim(str_replace($text, '', $html) . $append);
    };

    $html = $title;
    $matches = [];
    preg_match_all('/~([^~]+)~/', $title, $matches);
    foreach ($matches[0] as $i => $match) {
        $category = $matches[1][$i];
        $span = "<span class='tag'><span>$category</span></span>";
        $updateHtml($html, $match, $tags ? " $span" : '');
    }
    $matches = [];
    if (preg_match('/\[Subset - (.+)\]/', $title, $matches)) {
        $subset = $matches[1];
        $span = "<span class='tag'>"
            . "<span class='tag-label'>Subset</span>"
            . "<span class='tag-arrow'></span>"
            . "<span>$subset</span>"
            . "</span>";
        $updateHtml($html, $matches[0], $tags ? " $span" : '');
    }

    return $html;
}

/**
 * Render game breadcrumb prefix, with optional link on last crumb
 *
 * Format: `All Games » (console) » (game title)`.
 * If given data is for a subset, then `» Subset - (name)` is also added.
 */
function renderGameBreadcrumb(array|int $data, bool $addLinkToLastCrumb = true): string
{
    if (is_int($data)) {
        $data = getGameData($data);
    }
    // TODO refactor to Game
    $consoleID = $data['ConsoleID'];
    $consoleName = $data['ConsoleName'];

    // Return next crumb (i.e `» text`), either as a link or not
    $nextCrumb = fn ($text, $href = ''): string => " &raquo; " . ($href ? "<a href='$href'>$text</a>" : "<b>$text</b>");

    // Retrieve separate IDs and titles for main game and subset (if any)
    $getSplitData = function ($data) use ($consoleID): array {
        $gameID = $data['GameID'] ?? $data['ID'];
        $gameTitle = $data['GameTitle'] ?? $data['Title'];
        // Match and possibly split main title and subset
        $mainID = $gameID;
        $mainTitle = $gameTitle;
        $matches = [];
        if (preg_match('/(.+)(\[Subset - .+\])/', $gameTitle, $matches)) {
            $mainTitle = trim($matches[1]);
            $subset = $matches[2];
            $mainID = getGameIDFromTitle($mainTitle, $consoleID);
            $subsetID = $gameID;
            $renderedSubset = renderGameTitle($subset);
        }

        $renderedMain = renderGameTitle($mainTitle, tags: false);
        if ($renderedMain !== $mainTitle) {
            // In the rare case of a same-console derived game sharing identical
            // title with a base one, include category to solve ambiguity
            $baseTitle = trim(substr($mainTitle, strrpos($mainTitle, '~') + 1));
            $baseID = getGameIDFromTitle($baseTitle, $consoleID);
            if ($baseID) {
                $renderedMain = renderGameTitle($mainTitle);
            }
        }

        return [$mainID, $renderedMain, $subsetID ?? null, $renderedSubset ?? null];
    };

    $html = "<a href='/gameList.php'>All Games</a>"
        . $nextCrumb($consoleName, "/gameList.php?c=$consoleID");

    [$mainID, $renderedMain, $subsetID, $renderedSubset] = $getSplitData($data);
    $baseHref = (($addLinkToLastCrumb || $subsetID) && $mainID) ? "/game/$mainID" : '';
    $html .= $nextCrumb($renderedMain, $baseHref);
    if ($subsetID) {
        $html .= $nextCrumb($renderedSubset, $addLinkToLastCrumb ? "/game/$subsetID" : '');
    }

    return $html;
}

function renderGameCard(int|array $game): string
{
    $id = is_int($game) ? $game : ($game['GameID'] ?? $game['ID'] ?? null);

    if (empty($id)) {
        return __('legacy.error.error');
    }

    $data = [];
    if (is_array($game)) {
        $data = $game;
    }

    if (empty($data)) {
        $data = Cache::store('array')->rememberForever('game:' . $id . ':card-data', fn () => getGameData($id));
    }

    if (empty($data)) {
        return '';
    }

    $gameName = renderGameTitle($data['GameTitle'] ?? $data['Title'] ?? '');
    $consoleName = $data['Console'] ?? $data['ConsoleName'] ?? '';
    $icon = $data['GameIcon'] ?? $data['ImageIcon'] ?? null;

    $tooltip = "<div class='tooltip-body flex items-start' style='max-width: 400px'>";
    $tooltip .= "<img style='margin-right:5px' src='" . media_asset($icon) . "' width='64' height='64' />";
    $tooltip .= "<div>";
    $tooltip .= "<b>$gameName</b><br>";
    $tooltip .= $consoleName;

    $mastery = $game['Mastery'] ?? null;
    if (!empty($mastery)) {
        $tooltip .= "<div>$mastery</div>";
    }

    $tooltip .= "</div>";
    $tooltip .= "</div>";

    return $tooltip;
}

function RenderGameAlts(array $gameAlts, ?string $headerText = null): void
{
    echo "<div class='component gamealts'>";
    if ($headerText) {
        echo "<h2 class='text-h3'>$headerText</h2>";
    }
    echo "<table class='table-highlight'><tbody>";
    foreach ($gameAlts as $nextGame) {
        echo "<tr>";
        $consoleName = $nextGame['ConsoleName'];
        $points = $nextGame['Points'];
        $totalTP = $nextGame['TotalTruePoints'];
        $points = (int) $points;
        $totalTP = (int) $totalTP;

        $isFullyFeaturedGame = $consoleName != 'Hubs';
        if (!$isFullyFeaturedGame) {
            $consoleName = null;
        }

        $gameData = [
            'ID' => $nextGame['gameIDAlt'],
            'Title' => $nextGame['Title'],
            'ImageIcon' => $nextGame['ImageIcon'],
            'ConsoleName' => $consoleName,
        ];

        echo "<td>";
        echo gameAvatar($gameData, label: false);
        echo "</td>";

        echo "<td style='width: 100%' " . ($isFullyFeaturedGame ? '' : 'colspan="2"') . ">";
        echo gameAvatar($gameData, icon: false);
        echo "</td>";

        if ($isFullyFeaturedGame) {
            echo "<td>";
            echo "<span class='whitespace-nowrap'>$points points</span><span class='TrueRatio'> ($totalTP)</span>";
            echo "</td>";
        }

        echo "</tr>";
    }
    echo "</tbody></table>";
    echo "</div>";
}

function RenderMetadataTableRow(
    string $label,
    ?string $gameDataValue,
    ?array $gameHubs = null,
    array $altLabels = []
): void {
    $gameDataValues = !empty($gameDataValue) ? array_map('trim', explode(',', $gameDataValue)) : [];
    $unmergedKeys = array_keys($gameDataValues);

    if ($gameHubs) {
        $mergeMetadata = function ($hubCategory) use (&$gameHubs, &$gameDataValues, &$unmergedKeys) {
            $hubPrefix = "[$hubCategory - ";
            foreach ($gameHubs as $hub) {
                $title = $hub['Title'];
                if (str_starts_with($title, $hubPrefix)) {
                    if (str_starts_with($hubCategory, "Hack")) {
                        // for Hacks, we do want to display the hub category, but it
                        // should be normalized to "Hack - XXX".

                        // the hub name will always be "[Hacks - XXX]"
                        // strip the brackets and attempt to match the hub name explicitly
                        $value = substr($title, 1, -1);
                        $key = array_search($value, $gameDataValues);

                        // normalize to "Hack - XXX";
                        $value = str_replace("Hacks - ", "Hack - ", $value);

                        if ($key === false) {
                            // non-normalized value did not match, try normalized value
                            $key = array_search($value, $gameDataValues);
                        }
                    } else {
                        // strip the category and brackets and look for an exact match
                        $value = substr($title, strlen($hubPrefix), -1);
                        $key = array_search($value, $gameDataValues);
                    }

                    $link = "<a href=/game/" . $hub['gameIDAlt'] . ">$value</a>";

                    if ($key !== false) {
                        $gameDataValues[$key] = $link;
                        unset($unmergedKeys[$key]);
                    } else {
                        $gameDataValues[] = $link;
                    }
                }
            }
        };

        $mergeMetadata($label);

        foreach ($altLabels as $altLabel) {
            $mergeMetadata($altLabel);
        }
    }

    if (!empty($gameDataValues)) {
        foreach ($unmergedKeys as $key) {
            sanitize_outputs($gameDataValues[$key]);
        }

        echo "<tr>";
        echo "<td>$label</td>";
        echo "<td><b>" . implode(', ', $gameDataValues) . "</b></td>";
        echo "</tr>";
    }
}

function RenderLinkToGameForum(string $gameTitle, int $gameID, ?int $forumTopicID, int $permissions = Permissions::Unregistered): void
{
    sanitize_outputs(
        $gameTitle,
    );

    if (!empty($forumTopicID) && getTopicDetails($forumTopicID)) {
        echo "<a class='btn py-2 mb-2 block' href='/viewtopic.php?t=$forumTopicID'><span class='icon icon-md ml-1 mr-3'>💬</span>Official Forum Topic</a>";
    } else {
        if ($permissions >= Permissions::Developer) {
            echo "<form action='/request/game/generate-forum-topic.php' method='post' onsubmit='return confirm(\"Are you sure you want to create the official forum topic for this game?\")'>";
            echo csrf_field();
            echo "<input type='hidden' name='game' value='$gameID'>";
            echo "<button class='btn btn-link py-2 mb-2 w-full'><span class='icon icon-md ml-1 mr-3'>💬</span>Create Forum Topic</button>";
            echo "</form>";
        }
    }
}

function RenderRecentGamePlayers(array $recentPlayerData): void
{
    echo "<div class='component'>Recent Players:";
    echo "<table class='table-highlight'><tbody>";
    echo "<tr><th></th><th>User</th><th>When</th><th class='w-full'>Activity</th>";
    foreach ($recentPlayerData as $recentPlayer) {
        echo "<tr>";
        $userName = $recentPlayer['User'];
        $date = $recentPlayer['Date'];
        $activity = $recentPlayer['Activity'];
        sanitize_outputs(
            $userName,
            $activity
        );
        echo "<td>";
        echo userAvatar($userName, label: false);
        echo "</td>";
        echo "<td>";
        echo userAvatar($userName, icon: false);
        echo "</td>";
        echo "<td class='whitespace-nowrap'>$date</td>";
        echo "<td>$activity</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
    echo "</div>";
}

function RenderGameProgress(int $numAchievements, int $numEarnedCasual, int $numEarnedHardcore): void
{
    $pctComplete = 0;
    $pctHardcore = 0;
    $pctHardcoreProportion = 0;
    $title = '';

    if ($numEarnedCasual < 0) {
        $numEarnedCasual = 0;
    }

    if ($numAchievements) {
        $pctAwardedCasual = ($numEarnedCasual + $numEarnedHardcore) / $numAchievements;
        $pctAwardedHardcore = $numEarnedHardcore / $numAchievements;
        $pctAwardedHardcoreProportion = 0;
        if ($numEarnedHardcore > 0) {
            $pctAwardedHardcoreProportion = $numEarnedHardcore / ($numEarnedHardcore + $numEarnedCasual);
        }

        $pctComplete = sprintf("%01.0f", floor($pctAwardedCasual * 100.0));
        $pctHardcore = sprintf("%01.0f", floor($pctAwardedHardcore * 100.0));
        $pctHardcoreProportion = sprintf("%01.0f", $pctAwardedHardcoreProportion * 100.0);

        if ($numEarnedCasual && $numEarnedHardcore) {
            $title = "$pctHardcore% hardcore";
        }
    }
    $numEarnedTotal = $numEarnedCasual + $numEarnedHardcore;

    echo "<div class='w-40 my-2'>";
    echo "<div class='flex w-full items-center'>";
    echo "<div class='progressbar grow'>";
    echo "<div class='completion' style='width:$pctComplete%' title='$title'>";
    echo "<div class='completion-hardcore' style='width:$pctHardcoreProportion%'></div>";
    echo "</div>";
    echo "</div>";
    echo renderCompletionIcon($numEarnedTotal, $numAchievements, $pctHardcore);
    echo "</div>";
    echo "<div class='progressbar-label pr-5 -mt-1'>";
    if ($pctHardcore >= 100.0) {
        echo "Mastered";
    } else {
        echo "$pctComplete% complete";
    }
    echo "</div>";
    echo "</div>";
}

/**
 * Render completion icon, given that player achieved 100% set progress
 */
function renderCompletionIcon(
    int $awardedCount,
    int $totalCount,
    float|string $hardcoreRatio,
    bool $tooltip = false,
): string {
    if ($awardedCount === 0 || $awardedCount < $totalCount) {
        return "<div class='completion-icon'></div>";
    }
    [$icon, $class] = $hardcoreRatio == 100.0 ? ['👑', 'mastered'] : ['🎖️', 'completed'];
    $class = "completion-icon $class";
    $tooltipText = '';
    if ($tooltip) {
        $tooltipText = $hardcoreRatio == 100.0 ? 'Mastered (hardcore)' : 'Completed';
        $class .= ' tooltip';
    }

    return "<div class='$class' title='$tooltipText'>$icon</div>";
}
