<?php

namespace App\Controllers;

use Symfony\Component\HttpClient\HttpClient;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FetchGamesController extends Controller
{
    public function fetchGames()
    {
        $platform = request()->get('platform');

        if (!$platform) {
            return response()->json(['error' => 'Platform parameter is missing'], 400);
        }

        $httpClient = HttpClient::create();
        $url = "https://gamefaqs.gamespot.com/{$platform}/category/999-all";

        $games = [];
        $page = 0;

        do {

            $response = $httpClient->request('GET', $url . "$page=" . $page);

            if ($response->getStatusCode() !== 200) {
                return response()->json(['error' => 'Failed to fetch page', 'status' => $response->getStatusCode()], 400);
            }

            $content = $response->getContent();

            if (empty($content)) {
                return response()->json(['error' => 'Page content is empty'], 400);
            }

            $dom = new \DOMDocument();
            @$dom->loadHTML($content);

            $xpath = new \DOMXPath($dom);
            $gamesNode = $xpath->query('//td[@class="rtitle"]/a');

            if ($gamesNode->length === 0) {
                // Wyświetl komunikat o braku znalezionych elementów z XPath
                return response()->json(['error' => 'No games found with given XPath selector.'], 400);
            }

            foreach ($gamesNode as $node) {
                $gameUrl = "https://gamefaqs.gamespot.com" . $node->getAttribute('href');
                $gameName = trim($node->textContent);
                $gameData = $this->fetchGameDetails($gameUrl, $httpClient, $gameName);
                if ($gameData) {
                    $games[] = $gameData;
                }
            }
            $nextPageLink = $xpath->query('//ul[@class="paginate"]/li/a[contains(text(), "Next")]');
            $page++;
        } while ($nextPageLink->length > 0);

        $this->generateXlsx($games);
//        return response()->json($games);
    }

    public function fetchGamesPage()
    {
        $platform = request()->get('platform');
        if (!$platform) {
            return response()->json(['error' => 'Platform parameter is missing'], 400);
        }

        $page = request()->get('page');
        if (!$platform) {
            return response()->json(['error' => 'Page parameter is missing'], 400);
        }

        $httpClient = HttpClient::create();
        $url = "https://gamefaqs.gamespot.com/{$platform}/category/999-all?page={$page}";

        $games = [];

        $response = $httpClient->request('GET', $url);
        $content = $response->getContent();

        $dom = new \DOMDocument();
        @$dom->loadHTML($content);

        $xpath = new \DOMXPath($dom);
        $gameNodes = $xpath->query('//td[@class="rtitle"]/a');

        foreach ($gameNodes as $node) {
            $gameUrl = "https://gamefaqs.gamespot.com" . $node->getAttribute('href');
            $gameName = trim($node->textContent);
            $gameData = $this->fetchGameDetails($gameUrl, $httpClient, $gameName);
            if ($gameData) {
                $games[] = $gameData;
            }
        }

        $this->generateXlsx($games);
    }

    private function fetchGameDetails(string $url, $httpClient, string $gameName): ?array
    {
        $response = $httpClient->request('GET', $url);
        $content = $response->getContent();

        $dom = new \DOMDocument();
        @$dom->loadHTML($content);
        $xpath = new \DOMXPath($dom);

//        $name = $this->getXPathText($xpath, '//h1[@class="page-title"]');
        $platform = $this->getXPathText($xpath, '//ol[@class="list flex col1 nobg"]//li[1]//b[contains(text(), "Platform")]/following-sibling::a');
        $genre = $this->getXPathTextArray($xpath, '//ol[@class="list flex col1 nobg"]//li[2]//b[contains(text(), "Genre")]/following-sibling::a');
        $developer = $this->getXPathText($xpath, '//ol[@class="list flex col1 nobg"]//li[3]//b[contains(text(), "Developer/Publisher")]/following-sibling::a');
        $releaseDate = $this->formatDate($this->getXPathText($xpath, '//ol[@class="list flex col1 nobg"]//li[4]//b[contains(text(), "Release")]/following-sibling::a'));

        return [
            'name' => $gameName,
            'url' => $url,
            'platform' => $platform,
            'genre1' => $genre[0] ?? 'N/A',
            'genre2' => $genre[1] ?? 'N/A',
            'genre3' => $genre[2] ?? 'N/A',
            'genre4' => $genre[3] ?? 'N/A',
            'release_date' => $releaseDate,
            'developer' => $developer,
            'publisher' => $developer,
        ];
    }

    private function getXPathText(\DOMXPath $xpath, string $query): string
    {
        $node = $xpath->query($query)->item(0);
        return $node ? trim($node->textContent) : 'N/A';
    }

    private function getXPathTextArray(\DOMXPath $xpath, string $query): array
    {
        $nodes = $xpath->query($query);
        $values = [];
        foreach ($nodes as $node) {
            $values[] = trim($node->textContent);
        }
        return $values;
    }

    private function formatDate(string $date): string
    {
        $formattedDate = DateTime::createFromFormat('F j, Y', $date); // Przyjmuje, że format jest "Miesiąc dzień, rok"
        return $formattedDate ? $formattedDate->format('d.m.Y') : 'N/A';
    }

    private function generateXlsx(array $games)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Name');
        $sheet->setCellValue('B1', 'URL');
        $sheet->setCellValue('C1', 'Genre 1');
        $sheet->setCellValue('D1', 'Genre 2');
        $sheet->setCellValue('E1', 'Genre 3');
        $sheet->setCellValue('F1', 'Genre 4');
        $sheet->setCellValue('G1', 'Release Date');
        $sheet->setCellValue('H1', 'Developer');
        $sheet->setCellValue('I1', 'Publisher');

        $row = 2;
        foreach ($games as $game) {
            $sheet->setCellValue('A' . $row, $game['name']);
            $sheet->setCellValue('B' . $row, $game['url']);
            $sheet->setCellValue('C' . $row, $game['genre1']);
            $sheet->setCellValue('D' . $row, $game['genre2']);
            $sheet->setCellValue('E' . $row, $game['genre3']);
            $sheet->setCellValue('F' . $row, $game['genre4']);
            $sheet->setCellValue('G' . $row, $game['release_date']);
            $sheet->setCellValue('H' . $row, $game['developer']);
            $sheet->setCellValue('I' . $row, $game['publisher']);
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'games.xlsx';
        $temp_file = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($temp_file);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        readfile($temp_file);
        unlink($temp_file);
    }
}
