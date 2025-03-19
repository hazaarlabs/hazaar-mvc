{include file="functions.tpl"}
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hello Template</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }

        header {
            background-color: #333;
            color: #fff;
            padding: 10px 0;
            text-align: center;
        }

        .container {
            padding: 20px;
        }
    </style>
</head>

<body>
    <header>
        <h1>{greet $userName}!</h1>
    </header>
    <main>
        <div class="container">
            <h2>{$welcomeMessage|default:"Welcome to our site"}</h2>
            <p>
                {$introText|escape}
            </p>
            <h2>Details</h2>
            {if $items}
                {foreach from=$items item=item}
                    <div class="item">
                        <h3>{$item.title|escape}</h3>
                        <p>{$item.description|escape}</p>
                    </div>
                {/foreach}
            {else}
                <p>No items to display</p>
            {/if}
        </div>
        <div class="container">
            {include file="card.tpl"}
            {include file="card.tpl" card_title="Another Card Title" card_content="This is another sample card content." card_enable=true card_num=2}
        </div>
    </main>
</body>

</html>