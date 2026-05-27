"""
Skill: currency

Philippine Peso (PHP) formatting, parsing, and rounding utilities.
Ensures consistent 2-decimal-place display across all financial outputs.
"""

from typing import Union, Optional, Tuple
from decimal import Decimal, ROUND_HALF_UP
from enum import Enum


class RoundingMode(str, Enum):
    """Rounding modes for financial computation."""
    HALF_UP = "half_up"
    FLOOR = "floor"
    CEILING = "ceiling"


def format_peso(amount: Union[float, int, Decimal, str], include_symbol: bool = True) -> str:
    """
    Format a numeric amount as Philippine Peso string.

    Args:
        amount: The amount to format.
        include_symbol: Whether to include the symbol. Defaults to True.

    Returns:
        Formatted peso string (e.g., "1,234.56" or "1,234.56").

    Example:
        >>> format_peso(1234.5)
        '1,234.50'
        >>> format_peso(1000000.0)
        '1,000,000.00'
        >>> format_peso(0)
        '0.00'
        >>> format_peso(1234.567, include_symbol=False)
        '1,234.57'
    """
    if isinstance(amount, str):
        amount = float(amount.replace(",", "").replace("", "").strip())
    amount = float(amount)
    formatted = f"{amount:,.2f}"
    if include_symbol:
        return f"{formatted}"
    return formatted


def pesos_to_number(formatted: str) -> float:
    """
    Parse a formatted peso string back to a float.

    Args:
        formatted: A peso string like "1,234.56" or "1,234.56".

    Returns:
        The numeric value as a float.

    Example:
        >>> pesos_to_number("1,234.56")
        1234.56
        >>> pesos_to_number("50.00")
        50.0
    """
    cleaned = formatted.replace("", "").replace(",", "").strip()
    return float(cleaned)


def round_peso(amount: Union[float, Decimal], mode: RoundingMode = RoundingMode.HALF_UP) -> float:
    """
    Round a peso amount according to the specified mode.

    Args:
        amount: The amount to round.
        mode: Rounding strategy. Defaults to HALF_UP (standard rounding).

    Returns:
        Rounded float value.

    Example:
        >>> round_peso(1234.565)
        1234.57
        >>> round_peso(1234.564)
        1234.56
    """
    if isinstance(amount, float):
        amount = Decimal(str(amount))
    elif not isinstance(amount, Decimal):
        amount = Decimal(amount)

    if mode == RoundingMode.HALF_UP:
        result = amount.quantize(Decimal("0.01"), rounding=ROUND_HALF_UP)
    elif mode == RoundingMode.FLOOR:
        result = amount.quantize(Decimal("0.01"), rounding=ROUND_HALF_UP)
        result = int(result * 100) / 100.0
        return float(result)
    else:
        result = amount.quantize(Decimal("0.01"), rounding=ROUND_HALF_UP)

    return float(result)


def compute_vatable_amount(total: float, vat_percent: float = 12.0) -> Tuple[float, float]:
    """
    Compute the VAT-exclusive (vatable) amount and VAT from a total inclusive amount.

    Formula: vatable = total / (1 + vat_percent/100)

    Args:
        total: Total amount including VAT.
        vat_percent: VAT percentage. Defaults to 12% (Philippine VAT).

    Returns:
        A tuple of (vatable_amount, vat_amount).

    Example:
        >>> compute_vatable_amount(1120.0)
        (1000.0, 120.0)
    """
    divisor = 1.0 + vat_percent / 100.0
    vatable = total / divisor
    vat = total - vatable
    return round_peso(vatable), round_peso(vat)


def is_valid_peso_amount(value: Union[str, float, int]) -> bool:
    """
    Validate that a value represents a valid positive peso amount.

    Args:
        value: The value to validate.

    Returns:
        True if the value is a valid positive currency amount.
    """
    try:
        if isinstance(value, str):
            value = pesos_to_number(value)
        num = float(value)
        return num >= 0.0
    except (ValueError, TypeError):
        return False
