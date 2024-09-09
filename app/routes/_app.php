<?php

app()->get('/', function () {
    response()->json(['message' => 'Congrats!! You\'re on Leaf API']);
});

app()->get('/fetch-games', 'FetchGamesController@fetchGames');
app()->get('/fetch-games-page', 'FetchGamesController@fetchGamesPage');
