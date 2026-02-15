SELECT e.id,
    e.locked,
    (
        e.locked = 1
        OR e.date < NOW()
    ) as isLocked,
    e.name,
    e.date,
    e.capacity,
    (
        SELECT count(*)
        FROM event_users
        WHERE state = 1
            AND eventId = e.id
    ) as joined,
    (
        SELECT count(*)
        FROM event_users
        WHERE state = 2
            AND eventId = e.id
    ) as waitList,
    IF(eu.userId IS NOT NULL, eu.state, 0) as userState
FROM events e
    LEFT OUTER JOIN event_users eu ON eu.eventId = e.id