from myagent.safety import SafetyGate


def test_high_risk_requires_approval() -> None:
    gate = SafetyGate()
    decision = gate.evaluate("please delete file /tmp/x")
    assert decision.approval_required is True


def test_low_risk_no_approval() -> None:
    gate = SafetyGate()
    decision = gate.evaluate("tell me a joke")
    assert decision.approval_required is False
