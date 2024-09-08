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
            $page++;
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
                $gameData = $this->fetchGameDetails($gameUrl, $httpClient);
                if ($gameData) {
                    $games[] = $gameData;
                }
            }
            $hasNextPage = $xpath->query('//a[@class="paginate enabled" and contains(text(), "Next")]')->length > 0;
        } while ($hasNextPage);

        $this->generateXlsx($games);
//        return response()->json($games);
    }

    private function fetchGameDetails(string $url, $httpClient): ?array
    {
        $response = $httpClient->request('GET', $url);
        $content = $response->getContent();

        $dom = new \DOMDocument();
        @$dom->loadHTML($content);
        $xpath = new \DOMXPath($dom);

        $name = $this->getXPathText($xpath, '//h1[@class="page-title"]');
        $platform = $this->getXPathText($xpath, '//ol[@class="list flex col1 nobg"]//li[1]//b[contains(text(), "Platform")]/following-sibling::a');
        $genre = $this->getXPathText($xpath, '//ol[@class="list flex col1 nobg"]//li[2]//b[contains(text(), "Genre")]/following-sibling::a');
        $developer = $this->getXPathText($xpath, '//ol[@class="list flex col1 nobg"]//li[3]//b[contains(text(), "Developer/Publisher")]/following-sibling::a');
        $releaseDate = $this->getXPathText($xpath, '//ol[@class="list flex col1 nobg"]//li[4]//b[contains(text(), "Release")]/following-sibling::a');

        return [
            'name' => $name,
            'platform' => $platform,
            'genre' => $genre,
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

    private function generateXlsx(array $games)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Name');
        $sheet->setCellValue('B1', 'Genre');
        $sheet->setCellValue('C1', 'Platform');
        $sheet->setCellValue('D1', 'Release Date');
        $sheet->setCellValue('E1', 'Developer');
        $sheet->setCellValue('F1', 'Publisher');

        $row = 2;
        foreach ($games as $game) {
            $sheet->setCellValue('A' . $row, $game['name']);
            $sheet->setCellValue('B' . $row, $game['genre']);
            $sheet->setCellValue('C' . $row, $game['platform']);
            $sheet->setCellValue('D' . $row, $game['release_date']);
            $sheet->setCellValue('E' . $row, $game['developer']);
            $sheet->setCellValue('F' . $row, $game['publisher']);
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
