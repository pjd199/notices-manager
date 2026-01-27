# Advanced Notices Manager (WordPress Plugin)

**Advanced Notices Manager** is a productivity-focused WordPress plugin designed for organizations that manage high volumes of time-sensitive notices, such as News, Events, Jobs, and Volunteering. 

It replaces the standard WordPress post list with a unified, categorized dashboard that allows admins to manage post statuses, visibility tags, and stale content from a single screen.

---

## 🚀 Key Features

* **Unified Dashboard**: View all relevant categories (Introduction, News, Events, Prayer, Jobs, Volunteering) on one page.
* **One-Click Tag Switching**: Instantly change how a post is displayed using AJAX-powered dropdowns (Full, Short, List, or Parked).
* **Stale Content Alerts**: Visual highlights (yellow background) for news older than 21 days or events where the date has already passed.
* **Native-Style Row Actions**: Familiar WordPress "Edit | Clone | View | Bin" menu appears on hover.
* **One-Click Cloning**: Duplicate any notice (including custom metadata and tags) into a new draft with a single click.
* **Status Badges**: Clear visual indicators for Draft, Scheduled, Private, and Pending posts.
* **Category-Specific Logic**: Smart tag management prevents "tag bleeding" by using prefixed tags (e.g., `news-full` vs `jobs-full`).

---

## 🛠 Installation

1.  **Download** the `advanced-notices-manager.php` file.
2.  **Upload** it to your WordPress site via `wp-content/plugins/advanced-notices-manager/`.
3.  **Activate** the plugin through the 'Plugins' menu in WordPress.
4.  **Navigate** to **Posts > Notices Manager** to begin managing your content.

---

## 📂 Category & Tag Logic

The plugin relies on a specific tagging convention to determine how content is rendered on the front-end. To maintain a clean workflow, each category has its own set of tags:

### 1. Introduction (`intro`)
Designed for the "Welcome" or "Lead" section of your notices.
* `introduction-full`: Shows the full text.
* `introduction-parked`: Hides the post from public view (Parked).

### 2. Standard Categories (`news`, `prayer`, `jobs`, `volunteering`)
* `[category]-full`: The full post content is displayed.
* `[category]-short`: Displays the **Excerpt** only with a "Read More" link.
* `[category]-list`: Displays only the **Title** as a link.
* `[category]-parked`: The post is "Parked" (hidden from public view but kept for later).

### 3. Events (`events`)
Includes all tags from standard categories but adds custom date logic. 
* **Note**: The Manager table for Events prioritizes the **Event Start Date** (meta field: `event_start`) over the Publish Date for visual consistency.

---

## ⚠️ Stale Content Logic

To keep your notices fresh, the plugin automatically audits your posts:
* **General Categories**: Highlighted if the publish date is **older than 21 days**.
* **Events**: Highlighted if the **Event Start Date** has passed, regardless of the publish date.

---

## 📝 Admin Reference
* **Parked**: Use this for notices advertised months in advance. It fades the row in the manager so you know it's "in the garage" and not currently live.
* **Bin**: Moves the post to the Trash instantly.
* **Clone**: Perfect for recurring weekly items. It copies everything into a new draft, allowing for quick updates.

---

## 🔧 Requirements
* **WordPress**: 5.0+
* **PHP**: 7.4+
* **Dependencies**: Requires the post categories (`introduction`, `news`, `events`, `prayer`, `jobs`, `volunteering`) to exist in your WordPress setup.
