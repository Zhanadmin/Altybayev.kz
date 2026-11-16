<?php
declare(strict_types=1);

// TASK-030: общий кусок для страниц — карта «ру → таңба», помощники PHP/JS и список ру для поиска в формах.

use App\Services\ClanService;

if (!function_exists('clan_tamga_map')) {
    /** @return array<string,string> */
    function clan_tamga_map(): array
    {
        static $map = null;
        if ($map === null) {
            try {
                $map = (new ClanService())->tamgaMap();
            } catch (\Throwable $e) {
                $map = [];
            }
        }
        return $map;
    }

    /** Маленький значок таңба для ру (пустая строка, если знака нет). */
    function clan_tamga_html(?string $name): string
    {
        $key = mb_strtolower(trim((string)$name));
        $map = clan_tamga_map();
        if ($key === '' || empty($map[$key])) {
            return '';
        }
        return '<img class="clan-tamga" src="' . htmlspecialchars($map[$key], ENT_QUOTES) . '" alt="" loading="lazy"> ';
    }

    /** @return string[] названия ру для выпадающего поиска */
    function clan_registry_names(): array
    {
        static $names = null;
        if ($names === null) {
            $names = [];
            try {
                $svc = new ClanService();
                if ($svc->hasRegistry()) {
                    foreach ($svc->all() as $c) {
                        $names[] = (string)$c['name'];
                    }
                }
            } catch (\Throwable $e) {
            }
        }
        return $names;
    }
}
$__clanMap = clan_tamga_map();
$__clanNames = clan_registry_names();
?>
<style>
.clan-tamga { height: 1.15em; width: auto; max-width: 1.6em; object-fit: contain; vertical-align: -0.2em; margin-right: .2em; }
</style>
<script>
window.CLAN_TAMGA = <?= json_encode((object)$__clanMap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.CLAN_NAMES = <?= json_encode($__clanNames, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.clanTamga = function (name) {
    var k = String(name || '').trim().toLowerCase();
    var u = k && window.CLAN_TAMGA[k];
    if (!u) return '';
    return '<img class="clan-tamga" src="' + String(u).replace(/"/g, '&quot;') + '" alt="" loading="lazy"> ';
};
document.addEventListener('DOMContentLoaded', function () {
    if (!window.CLAN_NAMES.length) return;
    var dl = document.createElement('datalist');
    dl.id = 'clanRegistryList';
    window.CLAN_NAMES.forEach(function (n) { var o = document.createElement('option'); o.value = n; dl.appendChild(o); });
    document.body.appendChild(dl);
    ['editClanRu', 'formClanRu', 'editModalClanRu'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) { el.setAttribute('list', 'clanRegistryList'); el.setAttribute('autocomplete', 'off'); }
    });
});
</script>
