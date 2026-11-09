<?php

namespace App\Support;

/**
 * Awanse i spadki między sezonami (regulamin, punkt 9). Czysta funkcja bez bazy.
 *
 * Wejście: poziom ligi (1-10, 11 = Liga podwórkowa) => zespoły w kolejności tabeli końcowej,
 * każdy jako ['id' => int, 'human' => bool]. Wyjście: poziom => id w kolejności nowej listy.
 * Nagrody (tytuły, miejsca, MVP, Złota Piłka, Rękawice) liczą się wcześniej z prawdziwych tabel, także dla botów.
 *
 * Zasady (wersja z 15.10.2026, symulacja: notes/symulacja-awansow.md):
 *  1. w każdej lidze boty przesuwamy na koniec tabeli (ludzie i boty zachowują swoją kolejność),
 *  2. między sąsiednimi ligami 4 ostatnie spadają, 4 pierwsze z niższej awansują,
 *     z Ekstraklasy nikt nie awansuje, z podwórkowej nikt nie spada;
 *     kolejność w nowej lidze: spadkowicze z wyższej, pozostali, beniaminkowie,
 *  3. wypychanie botów: każdy bot zamienia się z najwyżej sklasyfikowanym człowiekiem pod nim (bez limitu lig).
 *     Wynik: najpierw wszyscy ludzie, potem wszystkie boty, każda grupa w kolejności listy po kroku 2,
 *     więc najlepszy bot otwiera listę botów pod ludźmi. Ligi zachowują swoje rozmiary.
 *     Człowiek, który wskoczył za bota, staje w nowej lidze za beniaminkami (rozstawienie w pucharze).
 */
final class Promotion
{
    public const MOVES = 4;

    /**
     * Wersja dla lig jedna pod drugą (poziom => tabela), np. ligi 1-10 i stara podwórkowa w jednej tabeli.
     *
     * @param  array<int, array<int, array{id: int, human: bool}>>  $tiers
     * @return array<int, array<int, int>>
     */
    public static function apply(array $tiers, int $moves = self::MOVES): array
    {
        ksort($tiers);
        $leagues = [];
        foreach ($tiers as $level => $teams) {
            $leagues[(string) $level] = ['parent' => $level > min(array_keys($tiers)) ? (string) ($level - 1) : null, 'up' => $moves, 'teams' => $teams];
        }

        $result = [];
        foreach (self::applyTree($leagues) as $key => $ids) {
            $result[(int) $key] = $ids;
        }

        return $result;
    }

    /**
     * Awanse i spadki w drzewie lig (nowa Liga podwórkowa: pod C klasą ligi 1.1–1.4, pod nimi 2.x …).
     * Klucze w kolejności listy (najwyższa liga pierwsza). Z ligi awansuje 'up' najlepszych do ligi 'parent',
     * a spada tyle, ile awansuje z lig pod nią razem; spadkowicze w kolejności tabeli trafiają kolejno do lig pod nią
     * (każda dostaje tylu, ilu oddała). Potem wypychanie botów jak w apply() (ligi zachowują rozmiary).
     *
     * @param  array<array-key, array{parent: string|null, up: int, teams: array<int, array{id: int, human: bool}>}>  $leagues
     * @return array<array-key, array<int, int>>
     */
    public static function applyTree(array $leagues): array
    {
        $keys = array_map(strval(...), array_keys($leagues));

        // 1. Boty na koniec każdej tabeli.
        $sorted = [];
        $children = array_fill_keys($keys, []);
        foreach ($leagues as $key => $league) {
            $sorted[$key] = array_merge(
                array_values(array_filter($league['teams'], fn ($t) => $t['human'])),
                array_values(array_filter($league['teams'], fn ($t) => ! $t['human'])),
            );
            if ($league['parent'] !== null && isset($children[$league['parent']])) {
                $children[$league['parent']][] = (string) $key;
            }
        }

        // 2. Awanse (najlepsi do ligi wyżej) i spadki (najgorsi, po kolei do lig pod spodem).
        $up = [];
        $intoFromAbove = array_fill_keys($keys, []);
        foreach ($keys as $key) {
            $hasParent = $leagues[$key]['parent'] !== null && isset($leagues[$leagues[$key]['parent']]);
            $up[$key] = $hasParent ? array_slice($sorted[$key], 0, $leagues[$key]['up']) : [];
        }

        $down = [];
        foreach ($keys as $key) {
            $count = array_sum(array_map(fn ($child) => count($up[$child]), $children[$key]));
            $upIds = array_column($up[$key], 'id');
            // Mała liga (np. pusta podwórkowa): awans i spadek nie mogą dotyczyć tych samych zespołów.
            $down[$key] = $count > 0
                ? array_values(array_filter(array_slice($sorted[$key], -$count), fn ($t) => ! in_array($t['id'], $upIds, true)))
                : [];

            $queue = $down[$key];
            foreach ($children[$key] as $child) {
                $intoFromAbove[$child] = array_splice($queue, 0, count($up[$child]));
            }
        }

        $flat = [];
        $sizes = [];
        foreach ($keys as $key) {
            $moving = array_column(array_merge($up[$key], $down[$key]), 'id');
            $stayers = array_values(array_filter($sorted[$key], fn ($t) => ! in_array($t['id'], $moving, true)));
            $promoted = [];
            foreach ($children[$key] as $child) {
                array_push($promoted, ...$up[$child]);
            }
            $league = array_merge($intoFromAbove[$key], $stayers, $promoted);
            $sizes[$key] = count($league);
            array_push($flat, ...$league);
        }

        // 3. Wypychanie botów: ludzie przed botami, kolejność w obu grupach jak po kroku 2.
        $order = array_merge(
            array_values(array_filter($flat, fn ($t) => $t['human'])),
            array_values(array_filter($flat, fn ($t) => ! $t['human'])),
        );

        // Kolejność w lidze: najpierw zespoły, które były tu po kroku 2 (spadkowicze, pozostali, beniaminkowie),
        // za nimi ludzie, którzy wskoczyli za boty; boty zawsze na końcu, w kolejności listy (najlepszy pierwszy).
        $leagueAfterStep2 = [];
        $index = [];
        $position = 0;
        foreach ($keys as $key) {
            foreach (array_splice($flat, 0, $sizes[$key]) as $team) {
                $leagueAfterStep2[$team['id']] = $key;
                $index[$team['id']] = $position++;
            }
        }

        $result = [];
        foreach ($keys as $key) {
            $members = array_splice($order, 0, $sizes[$key]);
            $sortKey = fn ($t) => [! $t['human'], $t['human'] && $leagueAfterStep2[$t['id']] !== $key, $index[$t['id']]];
            usort($members, fn ($a, $b) => $sortKey($a) <=> $sortKey($b));
            $result[$key] = array_column($members, 'id');
        }

        return $result;
    }

    /**
     * Kierunek zmiany ligi każdego przenoszonego zespołu (do oznaczeń w tabelach ostatniej kolejki):
     * id => 'up' (awans) albo 'down' (spadek), razem z zamianami z czyszczenia z botów.
     *
     * @param  array<int, array<int, array{id: int, human: bool}>>  $tiers
     * @return array<int, string>
     */
    public static function moves(array $tiers): array
    {
        ksort($tiers);
        $leagues = [];
        foreach ($tiers as $level => $teams) {
            $leagues[(string) $level] = ['parent' => $level > min(array_keys($tiers)) ? (string) ($level - 1) : null, 'up' => self::MOVES, 'teams' => $teams];
        }

        return self::movesTree($leagues);
    }

    /**
     * To samo dla drzewa lig (applyTree). Kierunek wynika z kolejności lig na liście: wyżej = awans.
     *
     * @param  array<array-key, array{parent: string|null, up: int, teams: array<int, array{id: int, human: bool}>}>  $leagues
     * @return array<int, string>
     */
    public static function movesTree(array $leagues): array
    {
        $rank = array_flip(array_map(strval(...), array_keys($leagues)));
        $from = [];
        foreach ($leagues as $key => $league) {
            foreach ($league['teams'] as $team) {
                $from[$team['id']] = $rank[(string) $key];
            }
        }

        $moves = [];
        foreach (self::applyTree($leagues) as $key => $ids) {
            foreach ($ids as $id) {
                if ($from[$id] !== $rank[(string) $key]) {
                    $moves[$id] = $from[$id] > $rank[(string) $key] ? 'up' : 'down';
                }
            }
        }

        return $moves;
    }
}
