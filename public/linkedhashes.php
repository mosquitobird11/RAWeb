<?php

use LegacyApp\Site\Enums\Permissions;

if (!authenticateFromCookie($user, $permissions, $userDetails, Permissions::Registered)) {
    abort(401);
}

$gameID = (int) request()->query('g');
if (empty($gameID)) {
    abort(404);
}

$gameData = getGameData($gameID);
$consoleName = $gameData['ConsoleName'];
$consoleID = $gameData['ConsoleID'];
$gameTitle = $gameData['Title'];
$gameIcon = $gameData['ImageIcon'];
$forumTopicID = $gameData['ForumTopicID'];
$hashes = getHashListByGameID($gameID);

RenderContentStart("Supported Game Files - $gameTitle");
?>
<div id="mainpage">
    <div id='fullcontainer'>
        <div class='navpath'>
            <?= renderGameBreadcrumb($gameData) ?>
            &raquo; <b>Supported Game Files</b>
        </div>

        <h3>List of Supported Game Files</h3>

        <?php
        echo gameAvatar($gameData, iconSize: 64);
        echo "<br><br>";

        echo "<p class='embedded p-4'><b>Game file hashes are used to confirm if two copies of a game file are identical. " .
             "We use it to ensure the player is using the same ROM as the achievement developer, or a compatible one." .
             "<br/><br/>RetroAchievements only hashes portions of larger game files to minimize load times, and strips " .
             "headers on smaller ones. Details on how the hash is generated for each system can be found " .
             "<a href='https://docs.retroachievements.org/Game-Identification/'>here</a>." .
             "</b></p>";

        echo "<p class='mt-4 mb-1'>There are currently <span class='font-bold'>" . count($hashes) . "</span> supported game file hashes registered for this game.</p>";

        echo "<ul>";
        $hasUnlabeledHashes = false;
        foreach ($hashes as $hash) {
            if (empty($hash['Name'])) {
                $hasUnlabeledHashes = true;
                continue;
            }

            $hashName = $hash['Name'];
            sanitize_outputs($hashName);
            echo "<li><p class='embedded p-4'><b>$hashName</b>";
            if (!empty($hash['Labels'])) {
                foreach (explode(',', $hash['Labels']) as $label) {
                    if (empty($label)) {
                        continue;
                    }

                    $image = "/assets/images/labels/" . $label . '.png';
                    if (file_exists(__DIR__ . $image)) {
                        echo ' <img class="inline-image" src="' . asset($image) . '">';
                    } else {
                        echo ' [' . $label . ']';
                    }
                }
            }

            echo '<br/><code> ' . $hash['Hash'] . '</code>';
            if (!empty($hash['User'])) {
                echo ' linked by ' . userAvatar($hash['User'], icon: false);
            }
            echo '</p></li>';
        }

        if ($hasUnlabeledHashes) {
            echo '<li><p class="embedded p-4"><b>Unlabeled Game File Hashes</b><br/>';
            foreach ($hashes as $hash) {
                if (!empty($hash['Name'])) {
                    continue;
                }

                echo '<code> ' . $hash['Hash'] . '</code>';
                if (!empty($hash['User'])) {
                    echo " linked by " . userAvatar($hash['User'], icon: false);
                }
                echo '<br/>';
            }
            echo "</p></li>";
        }

        echo "</ul>";
        echo "<br>";

        if ($forumTopicID > 0) {
            echo "Additional information for these hashes may be listed on the <a href='viewtopic.php?t=$forumTopicID'>official forum topic</a>.<br/>";
        }

        ?>
        <br>
    </div>
</div>
<?php RenderContentEnd(); ?>
