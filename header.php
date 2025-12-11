<style>
    /* Top header container */
    .top-header {
        background: linear-gradient(90deg, #1a73e8, #4285f4);
        padding: 15px 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: #fff;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        border-radius: 0 0 10px 10px;
    }

    /* Left side: welcome message */
    .top-header .left {
        font-size: 20px;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    /* Right side: buttons/links */
    .top-header .right a {
        background: #fff;
        color: #1a73e8;
        padding: 10px 18px;
        margin-left: 12px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .top-header .right a:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        background: #f1f1f1;
    }

    /* Special style for logout button */
    .top-header .right a.logout {
        background: #d93025;
        color: #fff;
    }

    .top-header .right a.logout:hover {
        background: #b1271b;
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    }

    /* Responsive: reduce padding on small screens */
    @media (max-width: 600px) {
        .top-header {
            flex-direction: column;
            align-items: flex-start;
            padding: 12px 20px;
        }
        .top-header .right {
            margin-top: 10px;
        }
        .top-header .right a {
            margin-left: 0;
            margin-right: 10px;
        }
    }
</style>

<div class="top-header">
    <div class="left">
        Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>
    </div>

    <div class="right">
        <a href="upload.php">Upload</a>
        <a href="download.php">Download</a>
        <a href="logout.php" class="logout">Logout</a>
    </div>
</div>
