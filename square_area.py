# square_area.py

def calculate_square_area(side_length):
    """
    Kiszámolja egy négyzet területét az oldalhossz alapján.
    A commit üzenetben erre a funkcióra fogunk hivatkozni!
    """
    # A terület az oldalhossz négyzete
    area = side_length * side_length
    return area

# Példa használat:
side = 5
result = calculate_square_area(side)

print(f"A(z) {side} egység oldalhosszú négyzet területe: {result} egység.")

# További példa:
side_b = 12
result_b = calculate_square_area(side_b)
print(f"A(z) {side_b} egység oldalhosszú négyzet területe: {result_b} egység.")
