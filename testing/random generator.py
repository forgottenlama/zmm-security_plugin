import json
import random
import string
import os

def generate_random_grid():
    grid = {}
    numbers = [str(i) for i in range(10)]  # 0–9
    letters = list(string.ascii_uppercase)  # A–Z

    for row in range(1, 7):
        for col in range(1, 7):
            value = random.choice(numbers) + random.choice(letters)
            grid[f"{row}_{col}"] = value

    return grid

# Vytvor priečinok, ak neexistuje
output_dir = "grid-cards"
os.makedirs(output_dir, exist_ok=True)

# Generovanie 50 kariet
for i in range(1, 551):
    grid = generate_random_grid()
    filename = os.path.join(output_dir, f"grid-{i}{i}{i}.json")
    with open(filename, "w", encoding="utf-8") as f:
        json.dump(grid, f, ensure_ascii=False, indent=4)

print("550 náhodných grid kariet bolo vygenerovaných do priečinka 'grid_cards'")
