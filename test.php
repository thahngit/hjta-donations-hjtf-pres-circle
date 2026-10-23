<?php
$name = $_GET['name'];

if ($name == "tyson"):
    sayHello($name);
else:
    decline($name);
endif;

function sayHello($name) {
    ?>
    <html>
        <head>
            <title>Hello</title>
        </head>
        <body>
            <div>
                <p>Hello, <?php echo $name; ?></p>
            </div>
        </body>
    </html>
    <?php
}

function decline($name) {
    ?>
    <html>
        <head>
            <title>Hello</title>
        </head>
        <body>
            <div>
                <p><?php echo $name; ?>, you are not tyson</p>
            </div>
        </body>
    </html>
    <?php
}
?>