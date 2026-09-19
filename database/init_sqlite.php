<?php
// database/init_sqlite.php
// Creates database/stms.db with all 11 tables and matching sample data
// Aligned with Web Tech pattern + HomeScore/AwayScore columns

$dbFile = __DIR__ . '/stms.db';
if (file_exists($dbFile)) {
    unlink($dbFile);
}

$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$schema = <<<SQL
CREATE TABLE COACH (
    CoachID INTEGER PRIMARY KEY AUTOINCREMENT,
    C_Name TEXT NOT NULL,
    Specialization TEXT NOT NULL,
    Email TEXT UNIQUE NOT NULL,
    Salary REAL CHECK (Salary > 0)
);

CREATE TABLE COACH_PHONE (
    CoachID INTEGER,
    Phone TEXT,
    PRIMARY KEY (CoachID, Phone),
    FOREIGN KEY (CoachID) REFERENCES COACH(CoachID) ON DELETE CASCADE
);

CREATE TABLE TOURNAMENT (
    TournamentID INTEGER PRIMARY KEY AUTOINCREMENT,
    T_Name TEXT NOT NULL,
    StartDate TEXT NOT NULL,
    EndDate TEXT NOT NULL,
    PrizeMoney REAL DEFAULT 0,
    Sport_Type TEXT DEFAULT 'Football',
    Location TEXT DEFAULT 'Main Campus',
    Max_Teams INTEGER DEFAULT 8
);

CREATE TABLE TEAM (
    TeamID INTEGER PRIMARY KEY AUTOINCREMENT,
    TeamName TEXT NOT NULL,
    Category TEXT,
    Sport_Type TEXT,
    HomeCity TEXT DEFAULT 'Dhaka',
    RegDate TEXT DEFAULT CURRENT_TIMESTAMP,
    CoachID INTEGER NOT NULL,
    TournamentID INTEGER,
    FOREIGN KEY (CoachID) REFERENCES COACH(CoachID) ON DELETE CASCADE,
    FOREIGN KEY (TournamentID) REFERENCES TOURNAMENT(TournamentID) ON DELETE SET NULL
);

CREATE TABLE PLAYER (
    PlayerID INTEGER PRIMARY KEY AUTOINCREMENT,
    P_Name TEXT NOT NULL,
    Position TEXT,
    JerseyNo INTEGER,
    Jersey_No INTEGER,
    DOB TEXT,
    Height REAL,
    Weight REAL,
    TeamID INTEGER NOT NULL,
    FOREIGN KEY (TeamID) REFERENCES TEAM(TeamID) ON DELETE CASCADE
);

CREATE TABLE PLAYER_PHONE (
    PlayerID INTEGER,
    Phone TEXT,
    PRIMARY KEY (PlayerID, Phone),
    FOREIGN KEY (PlayerID) REFERENCES PLAYER(PlayerID) ON DELETE CASCADE
);

CREATE TABLE REGISTRATION (
    RegistrationID INTEGER PRIMARY KEY AUTOINCREMENT,
    RegDate TEXT DEFAULT CURRENT_TIMESTAMP,
    RegFee REAL CHECK (RegFee >= 0),
    TeamID INTEGER NOT NULL,
    TournamentID INTEGER NOT NULL,
    FOREIGN KEY (TeamID) REFERENCES TEAM(TeamID) ON DELETE CASCADE,
    FOREIGN KEY (TournamentID) REFERENCES TOURNAMENT(TournamentID) ON DELETE CASCADE,
    UNIQUE (TeamID, TournamentID)
);

CREATE TABLE VENUE (
    VenueID INTEGER PRIMARY KEY AUTOINCREMENT,
    V_Name TEXT NOT NULL,
    Location TEXT NOT NULL,
    Capacity INTEGER CHECK (Capacity > 0),
    ContactNo TEXT
);

CREATE TABLE MATCHES (
    MatchID INTEGER PRIMARY KEY AUTOINCREMENT,
    TournamentID INTEGER NOT NULL,
    HomeTeamID INTEGER NOT NULL,
    AwayTeamID INTEGER NOT NULL,
    VenueID INTEGER NOT NULL,
    MatchDate TEXT NOT NULL,
    MatchTime TEXT NOT NULL,
    MatchStatus TEXT DEFAULT 'Scheduled',
    Score TEXT,
    Result TEXT,
    HomeScore INTEGER DEFAULT NULL,
    AwayScore INTEGER DEFAULT NULL,
    MatchType TEXT DEFAULT 'Group Stage',
    Match_Type TEXT DEFAULT 'Group Stage',
    FOREIGN KEY (TournamentID) REFERENCES TOURNAMENT(TournamentID) ON DELETE CASCADE,
    FOREIGN KEY (VenueID) REFERENCES VENUE(VenueID),
    FOREIGN KEY (HomeTeamID) REFERENCES TEAM(TeamID),
    FOREIGN KEY (AwayTeamID) REFERENCES TEAM(TeamID)
);

CREATE TABLE SPECTATOR (
    SpectatorID INTEGER PRIMARY KEY AUTOINCREMENT,
    S_Name TEXT NOT NULL,
    Email TEXT UNIQUE NOT NULL,
    Phone TEXT
);

CREATE TABLE TICKET (
    TicketID INTEGER PRIMARY KEY AUTOINCREMENT,
    MatchID INTEGER NOT NULL,
    SpectatorID INTEGER NOT NULL,
    SeatNo INTEGER CHECK (SeatNo > 0),
    Ticket_Type TEXT DEFAULT 'Standard',
    Price REAL CHECK (Price >= 0),
    Payment_Status TEXT DEFAULT 'Pending',
    Pay_Method TEXT DEFAULT 'Cash',
    FOREIGN KEY (MatchID) REFERENCES MATCHES(MatchID) ON DELETE CASCADE,
    FOREIGN KEY (SpectatorID) REFERENCES SPECTATOR(SpectatorID) ON DELETE CASCADE,
    UNIQUE (MatchID, SeatNo)
);

-- Coaches
INSERT INTO COACH (CoachID, C_Name, Specialization, Email, Salary) VALUES (101, 'Kamal Hossain', 'Football', 'kamal.h@gmail.com', 45000);
INSERT INTO COACH (CoachID, C_Name, Specialization, Email, Salary) VALUES (102, 'Rashed Karim', 'Cricket', 'rashed.k@yahoo.com', 52000);
INSERT INTO COACH (CoachID, C_Name, Specialization, Email, Salary) VALUES (103, 'Tanvir Ahmed', 'Football', 'tanvir.a@gmail.com', 48000);
INSERT INTO COACH (CoachID, C_Name, Specialization, Email, Salary) VALUES (104, 'Mahmudul Hasan', 'Basketball', 'mahmud.h@gmail.com', 40000);
INSERT INTO COACH (CoachID, C_Name, Specialization, Email, Salary) VALUES (105, 'Faisal Chowdhury', 'Cricket', 'faisal.c@gmail.com', 50000);

-- Coach Phones
INSERT INTO COACH_PHONE (CoachID, Phone) VALUES (101, '01711122233');
INSERT INTO COACH_PHONE (CoachID, Phone) VALUES (101, '01911122233');
INSERT INTO COACH_PHONE (CoachID, Phone) VALUES (102, '01822233344');
INSERT INTO COACH_PHONE (CoachID, Phone) VALUES (103, '01733344455');
INSERT INTO COACH_PHONE (CoachID, Phone) VALUES (104, '01644455566');
INSERT INTO COACH_PHONE (CoachID, Phone) VALUES (105, '01555566677');

-- Tournaments
INSERT INTO TOURNAMENT (TournamentID, T_Name, StartDate, EndDate, PrizeMoney, Sport_Type, Location, Max_Teams) VALUES (401, 'Inter-University Cricket League 2024', '2026-09-20', '2026-10-30', 150000, 'Cricket', 'Central Stadium', 8);
INSERT INTO TOURNAMENT (TournamentID, T_Name, StartDate, EndDate, PrizeMoney, Sport_Type, Location, Max_Teams) VALUES (402, 'Premier Football Cup 2026', '2026-10-01', '2026-11-15', 200000, 'Football', 'Main Campus Field', 12);
INSERT INTO TOURNAMENT (TournamentID, T_Name, StartDate, EndDate, PrizeMoney, Sport_Type, Location, Max_Teams) VALUES (403, 'National Inter-College Basketball Meet', '2026-11-01', '2026-11-20', 100000, 'Basketball', 'Indoor Gymnasium', 6);
INSERT INTO TOURNAMENT (TournamentID, T_Name, StartDate, EndDate, PrizeMoney, Sport_Type, Location, Max_Teams) VALUES (404, 'Summer Badminton Championship', '2026-08-01', '2026-08-15', 50000, 'Badminton', 'Sports Hall B', 8);

-- Teams
INSERT INTO TEAM (TeamID, TeamName, Category, Sport_Type, HomeCity, RegDate, CoachID, TournamentID) VALUES (201, 'Dhaka Gladiators', 'Cricket', 'Cricket', 'Dhaka', '2026-08-10', 102, 401);
INSERT INTO TEAM (TeamID, TeamName, Category, Sport_Type, HomeCity, RegDate, CoachID, TournamentID) VALUES (202, 'Chittagong Titans', 'Cricket', 'Cricket', 'Chittagong', '2026-08-12', 105, 401);
INSERT INTO TEAM (TeamID, TeamName, Category, Sport_Type, HomeCity, RegDate, CoachID, TournamentID) VALUES (203, 'Sylhet Thunder', 'Football', 'Football', 'Sylhet', '2026-08-15', 101, 402);
INSERT INTO TEAM (TeamID, TeamName, Category, Sport_Type, HomeCity, RegDate, CoachID, TournamentID) VALUES (204, 'Rajshahi Kings', 'Football', 'Football', 'Rajshahi', '2026-08-18', 103, 402);
INSERT INTO TEAM (TeamID, TeamName, Category, Sport_Type, HomeCity, RegDate, CoachID, TournamentID) VALUES (205, 'Barisal Warriors', 'Basketball', 'Basketball', 'Barisal', '2026-08-20', 104, 403);
INSERT INTO TEAM (TeamID, TeamName, Category, Sport_Type, HomeCity, RegDate, CoachID, TournamentID) VALUES (206, 'Khulna Tigers', 'Cricket', 'Cricket', 'Khulna', '2026-08-22', 102, 401);

-- Registrations
INSERT INTO REGISTRATION (RegistrationID, RegDate, RegFee, TeamID, TournamentID) VALUES (501, '2026-08-11', 15000, 201, 401);
INSERT INTO REGISTRATION (RegistrationID, RegDate, RegFee, TeamID, TournamentID) VALUES (502, '2026-08-13', 15000, 202, 401);
INSERT INTO REGISTRATION (RegistrationID, RegDate, RegFee, TeamID, TournamentID) VALUES (503, '2026-08-16', 20000, 203, 402);
INSERT INTO REGISTRATION (RegistrationID, RegDate, RegFee, TeamID, TournamentID) VALUES (504, '2026-08-19', 20000, 204, 402);
INSERT INTO REGISTRATION (RegistrationID, RegDate, RegFee, TeamID, TournamentID) VALUES (505, '2026-08-21', 12000, 205, 403);
INSERT INTO REGISTRATION (RegistrationID, RegDate, RegFee, TeamID, TournamentID) VALUES (506, '2026-08-23', 15000, 206, 401);

-- Players
INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, DOB, Height, Weight, TeamID) VALUES (301, 'Shakib Chowdhury', 'All-Rounder', 75, 75, '1998-03-24', 178, 72, 201);
INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, DOB, Height, Weight, TeamID) VALUES (302, 'Tamim Alom', 'Opening Batsman', 28, 28, '1999-05-12', 175, 76, 201);
INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, DOB, Height, Weight, TeamID) VALUES (303, 'Mushfiqur Rahman', 'Wicketkeeper', 15, 15, '1997-09-01', 165, 65, 201);
INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, DOB, Height, Weight, TeamID) VALUES (304, 'Taskin Hossain', 'Fast Bowler', 3, 3, '2000-04-03', 188, 80, 201);
INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, DOB, Height, Weight, TeamID) VALUES (305, 'Mehidy Hasan', 'Spin Bowler', 53, 53, '2001-10-25', 174, 68, 201);
INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, DOB, Height, Weight, TeamID) VALUES (306, 'Mahmudullah Riyad', 'Middle Order Batsman', 30, 30, '1996-02-04', 180, 74, 202);
INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, DOB, Height, Weight, TeamID) VALUES (307, 'Mustafizur Rahman', 'Fast Bowler', 90, 90, '1999-09-06', 182, 70, 202);
INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, DOB, Height, Weight, TeamID) VALUES (308, 'Litton Das', 'Wicketkeeper', 16, 16, '1998-10-13', 170, 66, 202);
INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, DOB, Height, Weight, TeamID) VALUES (309, 'Jamal Bhuyan', 'Midfielder', 6, 6, '1997-04-10', 176, 70, 203);
INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, DOB, Height, Weight, TeamID) VALUES (310, 'Anisur Rahman Zico', 'Goalkeeper', 1, 1, '1998-08-10', 184, 78, 203);
INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, DOB, Height, Weight, TeamID) VALUES (311, 'Topu Barman', 'Defender', 4, 4, '1996-12-20', 180, 75, 203);
INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, DOB, Height, Weight, TeamID) VALUES (312, 'Rakib Hossain', 'Forward', 10, 10, '2001-11-18', 172, 68, 204);
INSERT INTO PLAYER (PlayerID, P_Name, Position, JerseyNo, Jersey_No, DOB, Height, Weight, TeamID) VALUES (313, 'Sohel Rana', 'Midfielder', 8, 8, '1999-03-27', 174, 71, 204);

-- Player Phones
INSERT INTO PLAYER_PHONE (PlayerID, Phone) VALUES (301, '01710001111');
INSERT INTO PLAYER_PHONE (PlayerID, Phone) VALUES (302, '01810002222');
INSERT INTO PLAYER_PHONE (PlayerID, Phone) VALUES (303, '01910003333');
INSERT INTO PLAYER_PHONE (PlayerID, Phone) VALUES (304, '01610004444');
INSERT INTO PLAYER_PHONE (PlayerID, Phone) VALUES (305, '01510005555');

-- Venues
INSERT INTO VENUE (VenueID, V_Name, Location, Capacity, ContactNo) VALUES (601, 'Central University Stadium', 'Dhaka Main Campus', 5000, '+8801700000001');
INSERT INTO VENUE (VenueID, V_Name, Location, Capacity, ContactNo) VALUES (602, 'North Football Arena', 'Kuril Campus Ground', 3000, '+8801700000002');
INSERT INTO VENUE (VenueID, V_Name, Location, Capacity, ContactNo) VALUES (603, 'Mirpur Indoor Complex', 'Mirpur Sector 2', 1500, '+8801700000003');
INSERT INTO VENUE (VenueID, V_Name, Location, Capacity, ContactNo) VALUES (604, 'Bir Shreshtha Shaheed Ground', 'Dhanmondi', 4000, '+8801700000004');

-- Matches (including fixtures for Kamal Hossain / Sylhet Thunder Team 203)
INSERT INTO MATCHES (MatchID, TournamentID, HomeTeamID, AwayTeamID, VenueID, MatchDate, MatchTime, MatchStatus, Score, Result, HomeScore, AwayScore, MatchType, Match_Type)
VALUES (701, 401, 201, 202, 601, '2026-09-24', '02:30 PM', 'Scheduled', NULL, NULL, NULL, NULL, 'Group Stage', 'Group Stage');

INSERT INTO MATCHES (MatchID, TournamentID, HomeTeamID, AwayTeamID, VenueID, MatchDate, MatchTime, MatchStatus, Score, Result, HomeScore, AwayScore, MatchType, Match_Type)
VALUES (702, 401, 201, 206, 601, '2026-09-28', '04:00 PM', 'Scheduled', NULL, NULL, NULL, NULL, 'Group Stage', 'Group Stage');

INSERT INTO MATCHES (MatchID, TournamentID, HomeTeamID, AwayTeamID, VenueID, MatchDate, MatchTime, MatchStatus, Score, Result, HomeScore, AwayScore, MatchType, Match_Type)
VALUES (703, 402, 203, 204, 602, '2026-10-05', '03:30 PM', 'Scheduled', NULL, NULL, NULL, NULL, 'Semi Final', 'Semi Final');

INSERT INTO MATCHES (MatchID, TournamentID, HomeTeamID, AwayTeamID, VenueID, MatchDate, MatchTime, MatchStatus, Score, Result, HomeScore, AwayScore, MatchType, Match_Type)
VALUES (704, 403, 205, 201, 603, '2026-10-12', '05:00 PM', 'Scheduled', NULL, NULL, NULL, NULL, 'Final', 'Final');

INSERT INTO MATCHES (MatchID, TournamentID, HomeTeamID, AwayTeamID, VenueID, MatchDate, MatchTime, MatchStatus, Score, Result, HomeScore, AwayScore, MatchType, Match_Type)
VALUES (705, 401, 201, 202, 601, '2026-08-10', '03:00 PM', 'Completed', '180/4 vs 176/9', 'Dhaka Gladiators won by 5 wickets', 180, 176, 'Group Stage', 'Group Stage');

-- Additional matches for Sylhet Thunder (Team 203 - Kamal Hossain)
INSERT INTO MATCHES (MatchID, TournamentID, HomeTeamID, AwayTeamID, VenueID, MatchDate, MatchTime, MatchStatus, Score, Result, HomeScore, AwayScore, MatchType, Match_Type)
VALUES (706, 402, 203, 201, 602, '2026-10-15', '04:00 PM', 'Scheduled', NULL, NULL, NULL, NULL, 'Group Stage', 'Group Stage');

INSERT INTO MATCHES (MatchID, TournamentID, HomeTeamID, AwayTeamID, VenueID, MatchDate, MatchTime, MatchStatus, Score, Result, HomeScore, AwayScore, MatchType, Match_Type)
VALUES (707, 402, 204, 203, 604, '2026-08-25', '03:00 PM', 'Completed', '2 — 3', 'Sylhet Thunder won by 3-2', 2, 3, 'Group Stage', 'Group Stage');

-- Spectators
INSERT INTO SPECTATOR (SpectatorID, S_Name, Email, Phone) VALUES (801, 'Daud Ibrahim', 'customer@example.com', '01700000011');
INSERT INTO SPECTATOR (SpectatorID, S_Name, Email, Phone) VALUES (802, 'Siam Ahmed', 'siam.a@gmail.com', '01800000022');
INSERT INTO SPECTATOR (SpectatorID, S_Name, Email, Phone) VALUES (803, 'Nafisa Jahan', 'nafisa.j@gmail.com', '01900000033');
INSERT INTO SPECTATOR (SpectatorID, S_Name, Email, Phone) VALUES (804, 'Tanvir Hasan', 'tanvir.h@gmail.com', '01600000044');

-- Tickets
INSERT INTO TICKET (TicketID, MatchID, SpectatorID, SeatNo, Ticket_Type, Price, Payment_Status, Pay_Method) VALUES (901, 701, 801, 14, 'VIP', 2400.00, 'Paid', 'SSLCommerz');
INSERT INTO TICKET (TicketID, MatchID, SpectatorID, SeatNo, Ticket_Type, Price, Payment_Status, Pay_Method) VALUES (902, 701, 802, 15, 'VIP', 2400.00, 'Paid', 'bKash');
INSERT INTO TICKET (TicketID, MatchID, SpectatorID, SeatNo, Ticket_Type, Price, Payment_Status, Pay_Method) VALUES (903, 701, 803, 22, 'Standard', 200.00, 'Pending', 'Cash');
INSERT INTO TICKET (TicketID, MatchID, SpectatorID, SeatNo, Ticket_Type, Price, Payment_Status, Pay_Method) VALUES (904, 702, 801, 8, 'Premium', 500.00, 'Paid', 'Visa Card');
INSERT INTO TICKET (TicketID, MatchID, SpectatorID, SeatNo, Ticket_Type, Price, Payment_Status, Pay_Method) VALUES (905, 702, 804, 30, 'Standard', 200.00, 'Pending', 'Cash');
INSERT INTO TICKET (TicketID, MatchID, SpectatorID, SeatNo, Ticket_Type, Price, Payment_Status, Pay_Method) VALUES (906, 703, 802, 12, 'Standard', 250.00, 'Paid', 'Nagad');
SQL;

$pdo->exec($schema);
echo "STMS SQLite Database successfully re-initialized with HomeScore/AwayScore columns!\n";
