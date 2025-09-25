# Custom Grade Transmutation (local_customtransmute)

A Moodle *local* plugin that converts raw scores into a custom 65-100 scale without altering the original grade data.  
It adds a mirrored **Custom Transmuted** column for every grade item and keeps it perfectly synchronised, so teachers and learners can see both:

| Grade item | Raw mark (Moodle) | Custom Transmuted |
|------------|------------------:|------------------:|
| Quiz 1     | 3 / 5 points      | **75 / 100** |
| Assignment | 27 / 40 points    | **82 / 100** |

## 1  Why?

Philippine institutions often require the *60 % = 75 passing*, *0 % = 65* scale instead of Moodle’s default 0–100 %.  
This plugin applies that rule everywhere **without** touching core code or losing the original scores.

## 2  Formula
```l = 0.6 × n # 60 % threshold
if e ≥ l: 75 – 100 span
else if e ≥ l – 0.4: 74 # just below 60 %
else: floor – 74
```

In PHP (integer-rounded):

```php
function local_customtransmute_calculate(float $e, float $n, int $floor = 65): ?int {
    if ($e < 0 || $e > $n || $n <= 0) {
        return null;
    }
    $l = 0.6 * $n;
    if ($e >= $l) {
        $interval = 25 / ($n - $l);
        return round(100 - ($n - $e) * $interval);
    } elseif ($e >= $l - 0.4) {
        return 74;
    } else {
        $interval = (74 - $floor) / ($l - 1);
        return round(74 - $interval * ($l - 1 - $e));
    }
}
```

Key points
- 0 % → floor (default = 65)
- 59 % → 74 • 60 % → 75 • 100 % → 100
- Always rounded to a whole number.
The minimum floor can be changed in Site administration → Plugins → Local plugins → Custom Transmutation.

## 3 Features
- Whole-number output (no decimals)
- Works for every grade source (quizzes, assignments, manual, imports, APIs)
- Adds an automatic (Transmuted) column—no course editing required
- Leaves the raw grade untouched (safe for auditing)
- Demo page for quick “what-if” calculations
- Language strings ready for translation
- Follows Moodle coding guidelines (no DB hacks)

## 4 How it works
1. The plugin listens to the global \core\event\grade_updated event.
2. When any grade is written, it calculates the transmuted mark.
3. It creates/updates a shadow grade-item (manual, 0–100 scale) linked to the source item.
4. Standard grade reports then show a new column named “Activity name (Transmuted)”.
Because it is a normal grade-item you can:
- change its weight, hide/show it, put it in its own category, export it, etc.
- leave course totals unaffected by setting its weight = 0 (optional).

## 5 Installation
1. Copy or git-clone this folder to MOODLE_ROOT/local/customtransmute.
2. Log in as admin → Site administration → Notifications and complete the upgrade.
3. Configure the Minimum grade floor under
Site administration → Plugins → Local plugins → Custom Transmutation.
(Leave at 65 if unsure.)

## 6 Usage
6.1 Automatic gradebook columns
After installation any new or updated grade will spawn a “(Transmuted)” column.
To create columns for existing grades, open
Grades → Setup → Regrade all.

6.2 Demo page
Navigate to
Site administration → Plugins → Local plugins → Custom Transmutation
and click Grade Transmutation Demo.
Enter a raw score and the total items to see the transmuted result using the current floor.

6.3 Hiding or weighting columns
1. Grades → Setup
2. Click the edit icon next to the (Transmuted) item.
- Set Weight = 0 to exclude from course total, or
- Click Hide if you want students to see only raw or only transmuted grades.

## 7 Limitations & Roadmap
| Area |	Current behaviour |	Planned |
|------------|------------------:|------------------:|
| Activity review pages	| Show raw mark (Moodle default)	| Optional sub-plugin per activity to show both |
| Mobile app	| Shows both columns	| — |
| Unit tests	| Not yet included	| PHPUnit coverage for edge cases |


## 8 Uninstall
Site administration → Plugins → Plugins overview → Uninstall
All shadow grade-items will be removed; raw grades remain untouched.

## 9 License
GNU GPL v3 – see LICENSE.txt.

Enjoy accurate, policy-compliant grade displays without losing your raw data!




